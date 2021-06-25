<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\Callbacks\BreakOnFirstError;
use Phabalicious\Method\Callbacks\ExecuteCallback;
use Phabalicious\Method\Callbacks\FailOnMissingDirectory;
use Phabalicious\Scaffolder\CallbackOptions;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\Utilities\QuestionFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;

class ScriptMethod extends BaseMethod implements MethodInterface
{

    const HOST_SCRIPT_CONTEXT = 'host';
    const SCRIPT_COMMAND_LINE_DEFAULTS = 'scriptCommandLineDefaults';
    const SCRIPT_QUESTIONS = 'scriptQuestions';
    const SCRIPT_DATA = 'scriptData';
    const SCRIPT_CONTEXT = 'scriptContext';
    const SCRIPT_COMPUTED_VALUES = 'scriptComputedValues';

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

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        return parent::isRunningAppRequired($host_config, $context, $task) ||
            in_array($task, ['runScript']);
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
     *
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    public function runScript(HostConfig $host_config, TaskContextInterface $context)
    {
        $commands = $context->get(self::SCRIPT_DATA, []);
        $callbacks = $context->get('callbacks', []);
        $callbacks = Utilities::mergeData($this->callbacks, $callbacks);

        $options = new CallbackOptions();
        $options->addDefaultCallbacks();
        $options
            ->addCallback(new ExecuteCallback($this))
            ->addCallback(new BreakOnFirstError($this))
            ->addCallback(new FailOnMissingDirectory($this));

        // Allow other methods to add their callbacks.
        if (!empty($host_config['needs'])) {
            $context->getConfigurationService()->getMethodFactory()->alter(
                $host_config['needs'],
                'scriptCallbacks',
                $options
            );
        }

        $callbacks = Utilities::mergeData($options->getCallbacks(), $callbacks);

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
        $variables = Utilities::buildVariablesFrom($host_config, $context);
        $variables['computed'] = $this->resolveComputedValues($context, $variables);

        if (!empty($questions = $context->get(self::SCRIPT_QUESTIONS, []))) {
            $factory = new QuestionFactory();
            $questions = $factory->applyVariables($questions, $variables);
            $command_line_defaults = $context->get(self::SCRIPT_COMMAND_LINE_DEFAULTS, []);
            $variables['arguments'] = Utilities::mergeData(
                $variables['arguments'],
                $factory->askMultiple($questions, $context, [], function ($key, &$value) use ($command_line_defaults) {
                    if (isset($command_line_defaults[$key])) {
                        $value = $command_line_defaults[$key];
                    }
                })
            );
        }

        $replacements = Utilities::expandVariables($variables);
        $environment = Utilities::expandStrings($environment, $replacements);
        $environment = Utilities::validateScriptCommands($environment, $replacements);
        $environment = $context->getConfigurationService()->getPasswordManager()->resolveSecrets($environment);

        $commands = Utilities::expandStrings($commands, $replacements, []);
        $commands = Utilities::expandStrings($commands, $replacements);
        $commands = Utilities::validateScriptCommands($commands, $replacements);


        $commands = $context->getConfigurationService()->getPasswordManager()->resolveSecrets($commands);

        $context->set('host_config', $host_config);

        try {
            $result = $this->runScriptImpl(
                $root_folder,
                $commands,
                $context,
                $callbacks,
                $environment
            );

            $context->setResult('exitCode', $result ? $result->getExitCode() : 0);
            $context->setResult('commandResult', $result);
        } catch (UnknownReplacementPatternException $e) {
            $context->io()->error('Unknown replacement in line `' . $e->getOffendingLine() .'`');

            $matches = [];
            if (preg_match_all('/%arguments\.(.*?)%/', $e->getOffendingLine(), $matches)) {
                foreach ($matches[1] as $a) {
                    $context->io()->error('Missing argument: `' . $a . '`!');
                }
                throw $e;
            }

            $printed_replacements = array_map(function ($key) use ($replacements) {
                $value = $replacements[$key];
                if (strlen($value) > 40) {
                    $value = substr($value, 0, 40) . '…';
                }
                return [$key, $value];
            }, array_keys($replacements));

            $context->io()->table(['Key', 'Replacement'], $printed_replacements);

            throw $e;
        }
    }

    /**
     * @param string $root_folder
     * @param array $commands
     * @param TaskContextInterface $context
     * @param array $callbacks
     * @param array $environment
     *
     * @return CommandResult|null
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    private function runScriptImpl(
        string $root_folder,
        array $commands,
        TaskContextInterface $context,
        array $callbacks = [],
        array $environment = []
    ) : ?CommandResult {
        $command_result = new CommandResult(0, []);
        $context->set('break_on_first_error', $this->getBreakOnFirstError());

        $shell = $context->getShell();
        $shell->setOutput($context->getOutput());
        $shell->applyEnvironment($environment);

        $shell->pushWorkingDir($root_folder);

        foreach ($commands as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $result = Utilities::extractCallback($line);
            $callback_handled = false;
            if ($result) {
                [$callback_name, $args] = $result;
                $callback_handled = $this->executeCallback($context, $callbacks, $callback_name, $args);
            }
            if (!$callback_handled) {
                $command_result = $shell->run($line, false, false);
                $context->setCommandResult($command_result);

                if ($command_result->failed() && $this->getBreakOnFirstError()) {
                    $shell->popWorkingDir();
                    return $command_result;
                }
            }
        }

        $shell->popWorkingDir();
        return $command_result;
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
     * Execute callback.
     *
     * @param TaskContextInterface $context
     * @param array $callbacks
     * @param string $callback_name
     * @param array $args
     *
     * @return bool
     * @throws MissingScriptCallbackImplementation
     */
    private function executeCallback(
        TaskContextInterface $context,
        array $callbacks,
        string $callback_name,
        array $args
    ): bool {
        if (!isset($callbacks[$callback_name])) {
            return false;
        }

        /** @var \Phabalicious\Scaffolder\Callbacks\CallbackInterface $fn */
        $fn = $callbacks[$callback_name];
        if (!($fn instanceof CallbackInterface)) {
            throw new MissingScriptCallbackImplementation($callback_name, $callbacks);
        }
        $fn->handle($context, ...$args);

        return true;
    }

    /**
     * @param HostConfig $config
     * @param string $task
     * @param TaskContextInterface $context
     *
     * @throws MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    public function runTaskSpecificScripts(HostConfig $config, string $task, TaskContextInterface $context)
    {
        $this->logger->debug("Try runTaskSpecific scripts for " . $task);

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
            $context->set(self::SCRIPT_DATA, $script);
            $this->runScript($config, $context);
        }

        if (!empty($config[$task]) && !Utilities::isAssocArray($config[$task])) {
            $script = $config[$task];
            $this->logger->info(sprintf(
                'Running host-specific script for task `%s` and host `%s`',
                $task,
                $config->getConfigName()
            ));
            $context->set(self::SCRIPT_DATA, $script);
            $this->runScript($config, $context);
        }
    }

    /**
     * Run fallback scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     *
     * @throws MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
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
     *
     * @throws MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::preflightTask($task, $config, $context);
        $this->runTaskSpecificScripts($config, $task . 'Prepare', $context);
        if ($current_stage = $context->get('currentStage')) {
            $current_stage = ucfirst($current_stage);
            $this->runTaskSpecificScripts($config, $task . $current_stage . 'Prepare', $context);
        }
    }

    /**
     * Run postflight scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     *
     * @throws MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
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
        if ($current_stage = $context->get('currentStage')) {
            $current_stage = ucfirst($current_stage);
            $this->runTaskSpecificScripts($config, $task . $current_stage . 'Finished', $context);
        }

        $this->runTaskSpecificScripts($config, $task . 'Finished', $context);

        foreach ([$task . 'Prepare', $task, $task . 'Finished'] as $t) {
            unset($this->handledTaskSpecificScripts[$t]);
        }
    }

    private function resolveComputedValues(TaskContextInterface $context, $variables): array
    {
        $shell = $context->getShell();
        $result = [];
        $computed_values = $context->get(self::SCRIPT_COMPUTED_VALUES, []);
        $replacements = Utilities::expandVariables($variables);
        $computed_values = Utilities::expandStrings($computed_values, $replacements);

        foreach ($computed_values as $key => $cmd) {
            $cmd_result = $shell->run($cmd, true);
            $output = '';
            if ($cmd_result->succeeded()) {
                $output = trim(implode("\n", $cmd_result->getOutput()));
            }
            $result[$key] = $output == "" ? $cmd_result->getExitCode() : $output;
            $this->logger->info(sprintf("Results for computed value %s: %s", $key, $result[$key]));
        }

        return $result;
    }

    public static function prepareContextFromScript(TaskContextInterface $context, array $script_data)
    {

        $script_context = $script_data['context'] ?? ScriptMethod::HOST_SCRIPT_CONTEXT;
        $script_questions = $script_data['questions'] ?? [];
        $computed_values = $script_data['computedValues'] ?? [];
        if (!empty($script_data['script'])) {
            $script_data = $script_data['script'];
        }

        $context->set(ScriptMethod::SCRIPT_DATA, $script_data);
        $context->set(ScriptMethod::SCRIPT_CONTEXT, $script_context);
        $context->set(ScriptMethod::SCRIPT_QUESTIONS, $script_questions);
        $context->set(ScriptMethod::SCRIPT_COMPUTED_VALUES, $computed_values);
    }
}
