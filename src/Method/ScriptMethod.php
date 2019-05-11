<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Symfony\Component\Console\Style\SymfonyStyle;

class ScriptMethod extends BaseMethod implements MethodInterface
{

    private $breakOnFirstError = true;
    private $callbacks = [];
    private $handledTaskSpecificScripts = [];

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
        $service->checkForValidFolderName('rootFolder');
    }


    /**
     * Set default callbacks, these are globally available.
     *
     * @param array $callbacks
     */
    public function setDefaultCallbacks(array $callbacks)
    {
        $this->callbacks = $callbacks;
    }


    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws MissingScriptCallbackImplementation
     */
    public function runScript(HostConfig $host_config, TaskContextInterface $context)
    {
        $commands = $context->get('scriptData', []);
        $variables = $context->get('variables', []);
        $callbacks = $context->get('callbacks', []);
        $callbacks = Utilities::mergeData($this->callbacks, $callbacks);

        $environment = $context->get('environment', []);
        if (!$context->getShell()) {
            $context->setShell($host_config->shell());
        }

        $root_folder = isset($host_config['rootFolder'])
                ? $host_config['rootFolder']
                : '.';
        $root_folder = $context->get('rootFolder', $root_folder);

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

        $context->set('host_config', $host_config);

        try {
            $result = $this->runScriptImpl(
                $root_folder,
                $commands,
                $context,
                $callbacks,
                $environment,
                $replacements
            );

            $context->setResult('exitCode', $result ? $result->getExitCode() : 0);
            $context->setResult('commandResult', $result);
        } catch (UnknownReplacementPatternException $e) {
            $context->io()->error('Unknown replacement in line ' . $e->getOffendingLine());

            $printed_replacements = array_map(function ($key) use ($replacements) {
                $value = $replacements[$key];
                if (strlen($value) > 40) {
                    $value = substr($value, 0, 40) . '…';
                }
                return [$key, $value];
            }, array_keys($replacements));
            $context->io()->table(['Key', 'Replacement'], $printed_replacements);
        }
    }

    /**
     * @param string $root_folder
     * @param array $commands
     * @param TaskContextInterface $context
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
        TaskContextInterface $context,
        array $callbacks = [],
        array $environment = [],
        array $replacements = []
    ) {
        $command_result = null;
        $context->set('break_on_first_error', $this->getBreakOnFirstError());

        $shell = $context->getShell();
        $shell->setOutput($context->getOutput());
        $shell->cd($root_folder);
        $shell->applyEnvironment($environment);

        $result = $this->validateReplacements($commands);
        if ($result !== true) {
            throw new UnknownReplacementPatternException($result, $replacements);
        }
        $result = $this->validateReplacements($environment);
        if ($result !== true) {
            throw new UnknownReplacementPatternException($result, $replacements);
        }

        foreach ($commands as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $result = Utilities::extractCallback($line);
            $callback_handled = false;
            if ($result) {
                list($callback_name, $args) = $result;
                $callback_handled = $this->executeCallback($context, $callbacks, $callback_name, $args);
            }
            if (!$callback_handled) {
                $command_result = $shell->run($line, false, false);
                $context->setCommandResult($command_result);

                if ($command_result->failed() && $this->getBreakOnFirstError()) {
                    return $command_result;
                }
            }
        }

        return $command_result;
    }

    /**
     * @param $strings
     * @return bool
     */
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
     * @param TaskContextInterface $context
     * @param $callbacks
     * @param $callback
     * @param $args
     *
     * @return bool
     * @throws MissingScriptCallbackImplementation
     */
    private function executeCallback(TaskContextInterface $context, $callbacks, $callback, $args)
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


    /**
     *
     * @throws \Exception
     */
    public function handleExecuteCallback()
    {
        $args = func_get_args();
        $context = array_shift($args);
        $task_name = array_shift($args);

        $this->executeCommand($context, $task_name, $args);
    }

    /**
     * @param TaskContextInterface $context
     * @param $flag
     */
    public function handleFailOnErrorDeprecatedCallback(TaskContextInterface $context, $flag)
    {
        $this->logger->warning('`fail_on_error` is deprecated, please use `breakOnFirstError()`');
        $this->handleFailOnErrorCallback($context, $flag);
    }

    /**
     * @param TaskContextInterface $context
     * @param $flag
     */
    public function handleFailOnErrorCallback(TaskContextInterface $context, $flag)
    {
        $context->set('break_on_first_error', $flag);
        $this->setBreakOnFirstError($flag);
    }

    /**
     * @param TaskContextInterface $context
     * @param $dir
     * @throws \Exception
     */
    public function handleFailOnMissingDirectoryCallback(TaskContextInterface $context, $dir)
    {
        if (!$context->getShell()->exists($dir)) {
            throw new \Exception('`' . $dir . '` . does not exist!');
        }
    }

    /**
     * @return bool
     */
    public function getBreakOnFirstError(): bool
    {
        return $this->breakOnFirstError;
    }

    /**
     * @param bool $flag
     */
    public function setBreakOnFirstError(bool $flag)
    {
        $this->breakOnFirstError = $flag;
    }

    /**
     * @param HostConfig $config
     * @param string $task
     * @param TaskContextInterface $context
     * @throws MissingScriptCallbackImplementation
     */
    public function runTaskSpecificScripts(HostConfig $config, string $task, TaskContextInterface $context)
    {
        $this->handledTaskSpecificScripts[$task] = true;

        $common_scripts = $context->getConfigurationService()->getSetting('common', []);
        $type = $config['type'];
        if (!empty($common_scripts[$type]) && is_array($common_scripts[$type])) {
            $this->logger->warning(
                'Found old-style common scripts! Please regroup by common > taskName > type > commands.'
            );
            return;
        }

        if (!empty($common_scripts[$task][$type])) {
            $script = $common_scripts[$task][$type];
            $this->logger->info(sprintf(
                'Running common script for task `%s` and type `%s`',
                $task,
                $type
            ));
            $context->set('scriptData', $script);
            $this->runScript($config, $context);
        }

        if (!empty($config[$task]) && !Utilities::isAssocArray($config[$task])) {
            $script = $config[$task];
            $this->logger->info(sprintf(
                'Running host-specific script for task `%s` and host `%s`',
                $task,
                $config['configName']
            ));
            $context->set('scriptData', $script);
            $this->runScript($config, $context);
        }
    }

    /**
     * Run fallback scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     * @throws MissingScriptCallbackImplementation
     */
    public function fallback(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::fallback($task, $config, $context);
        $this->runTaskSpecificScripts($config, $task, $context);
    }

    /**
     * Run preflight scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     * @throws MissingScriptCallbackImplementation
     */
    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::preflightTask($task, $config, $context);
        $this->runTaskSpecificScripts($config, $task . 'Prepare', $context);
    }

    /**
     * Run postflight scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     * @throws MissingScriptCallbackImplementation
     */
    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::postflightTask($task, $config, $context);

        // Make sure, that task-specific scripts get called.
        // Other methods may have called them already, so
        // handledTaskSpecificScripts keep track of them.
        if (empty($this->handledTaskSpecificScripts[$task])) {
            $this->runTaskSpecificScripts($config, $task, $context);
        }
        $this->runTaskSpecificScripts($config, $task . 'Finished', $context);

        foreach ([$task . 'Prepare', $task, $task . 'Finished'] as $t) {
            unset($this->handledTaskSpecificScripts[$t]);
        }
    }
}
