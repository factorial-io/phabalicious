<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScriptMethod extends BaseMethod implements MethodInterface
{

    private $breakOnFirstError = true;

    public function getName(): string
    {
        return 'script';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == 'script';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'rootFolder' => $configuration_service->getFabfilePath(),
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $service = new ValidationService($config, $errors, 'host-config');
        $service->hasKey('rootFolder', 'The root-folder of your configuration.');
    }


    /**
     * @param HostConfig $host_config
     * @param TaskContext $context
     * @throws MissingScriptCallbackImplementation
     */
    public function runScript(HostConfig $host_config, TaskContext $context)
    {
        $commands = $context->get('scriptData', []);
        $variables = $context->get('variables', []);
        $callbacks = $context->get('callbacks', []);
        $environment = $context->get('environment', []);
        $root_folder = isset($host_config['siteFolder'])
            ? $host_config['siteFolder']
            : isset($host_config['rootFolder'])
                ? $host_config['rootFolder']
                : '.';

        if (!empty($host_config['environment'])) {
            $environment = Utilities::mergeData($environment, $host_config['environment']);
        }
        $variables = Utilities::mergeData($variables, [
            'host' => $host_config->raw(),
            'settings' => $context->getConfigurationService()
                ->getAllSettings(['hosts', 'dockerHosts']),
        ]);

        $replacements = Utilities::expandVariables($variables);
        $commands = Utilities::expandStrings($commands, $replacements);
        $commands = Utilities::expandStrings($commands, $replacements);
        $environment = Utilities::expandStrings($environment, $replacements);

        $callbacks['execute'] = [$this, 'handleExecuteCallback'];
        $callbacks['fail_on_error'] = [$this, 'handleFailOnErrorDeprecatedCallback'];
        $callbacks['breakOnFirstError'] = [$this, 'handleFailOnErrorCallback'];
        $callbacks['fail_on_missing_directory'] = [
            $this,
            'handleFailOnMissingDirectoryCallback'
        ];

        try {
            $this->runScriptImpl(
                $root_folder,
                $commands,
                $host_config,
                $context,
                $callbacks,
                $environment,
                $replacements
            );
        } catch (UnknownReplacementPatternException $e) {
            $context->getOutput()
                ->writeln('<error>Unknown replacement in line ' . $e->getOffendingLine() . '</error>');

            $printed_replacements = array_map(function ($key) use ($replacements) {
                $value = $replacements[$key];
                if (strlen($value) > 40) {
                    $value = substr($value, 0, 40) . '…';
                }
                return [$key, $value];
            }, array_keys($replacements));
            $style = new SymfonyStyle($context->getInput(), $context->getOutput());
            $style->table(['Key', 'Replacement'], $printed_replacements);
        }
    }

    /**
     * @param string $root_folder
     * @param array $commands
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param TaskContext $context
     * @param array $callbacks
     * @param array $environment
     * @param array $replacements
     *
     * @return \Phabalicious\ShellProvider\CommandResult
     * @throws MissingScriptCallbackImplementation
     * @throws UnknownReplacementPatternException
     */
    private function runScriptImpl(
        string $root_folder,
        array $commands,
        HostConfig $host_config,
        TaskContext $context,
        array $callbacks = [],
        array $environment = [],
        array $replacements = []
    ) {
        $command_result = null;
        $context->set('break_on_first_error', $this->getBreakOnFirstError());
        $context->set('host_config', $host_config);

        $host_config->shell()->cd($root_folder);
        $host_config->shell()->applyEnvironment($environment);
        $host_config->shell()->setOutput($context->getOutput());

        $result = $this->validateReplacements($commands);
        if ($result !== true) {
            throw new UnknownReplacementPatternException($result, $replacements);
        }
        $result = $this->validateReplacements($environment);
        if ($result !== true) {
            throw new UnknownReplacementPatternException($result, $replacements);
        }

        foreach ($commands as $line) {
            $result = Utilities::extractCallback($line);
            $callback_handled = false;
            if ($result) {
                list($callback_name, $args) = $result;
                $callback_handled = $this->executeCallback($context, $callbacks, $callback_name, $args);
            }
            if (!$callback_handled) {
                $line = $this->expandCommand($line, $host_config);
                $command_result = $host_config->shell()->run($line);
                $context->setCommandResult($command_result);

                if ($command_result->failed() && $this->getBreakOnFirstError()) {
                    return $command_result;
                }
            }
        }

        return $command_result;
    }

    private function validateReplacements($strings)
    {
        foreach ($strings as $line) {
            if (preg_match('/\%(\S*)\%/', $line)) {
                return $line;
            }
        }
        return true;
    }


    /**
     * Execute callback.
     *
     * @param \Phabalicious\Method\TaskContext $context
     * @param $callbacks
     * @param $callback
     * @param $args
     *
     * @return bool
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    private function executeCallback(TaskContext $context, $callbacks, $callback, $args)
    {
        if (!isset($callbacks[$callback])) {
            return false;
        }

        if (!is_callable($callbacks[$callback])) {
            throw new MissingScriptCallbackImplementation($callback, $callbacks);
        }
        $fn = $callbacks[$callback];
        $args_with_context = $args;
        array_unshift($args_with_context, $context);
        call_user_func_array($fn, $args_with_context);

        return true;
    }

    private function expandCommand($line, HostConfig $host_config)
    {
        if (empty($host_config['executables'])) {
            return $line;
        }
        $pattern = implode('|', array_map(function ($elem) {
            return preg_quote('#!' . $elem) . '|' . preg_quote('$$' . $elem);
        }, array_keys($host_config['executables'])));

        $cmd = preg_replace_callback('/' . $pattern . '/g', function ($elem) use ($host_config) {
            return $host_config['executables'][substr($elem, 2)];
        }, $line);

        return $cmd;
    }

    public function handleExecuteCallback()
    {
        $args = func_get_args();
        $context = array_shift($args);
        $task_name = array_shift($args);

        $this->executeCommand($context, $task_name, $args);
    }

    public function handleFaileOnErrorDeprecatedCallback(TaskContext $context, $flag) {
        $this->logger->warning('`fail_on_error` is deprecated, please use `breakOnFirstError()`');
        $this->handleFailOnErrorCallback($context, $flag);
    }

    public function handleFailOnErrorCallback(TaskContext $context, $flag)
    {
        $context->set('break_on_first_error', $flag);
        $this->setBreakOnFirstError($flag);
    }

    public function getBreakOnFirstError(): bool
    {
        return $this->breakOnFirstError;
    }

    public function setBreakOnFirstError(bool $flag)
    {
        $this->breakOnFirstError = $flag;
    }
}