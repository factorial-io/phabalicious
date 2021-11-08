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
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\QuestionFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Symfony\Component\Yaml\Yaml;

class ScriptMethod extends BaseMethod implements MethodInterface
{

    const SCRIPT_COMMAND_LINE_DEFAULTS = 'scriptCommandLineDefaults';
    const SCRIPT_QUESTIONS = 'scriptQuestions';
    const SCRIPT_DATA = 'scriptData';
    const SCRIPT_CONTEXT = 'scriptContext';
    const SCRIPT_CONTEXT_DATA = 'scriptContextData';
    const SCRIPT_COMPUTED_VALUES = 'scriptComputedValues';
    const SCRIPT_CALLBACKS = 'callbacks';
    const SCRIPT_CLEANUP = 'scriptCleanup';


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
     * @throws \Phabalicious\Exception\ValidationFailedException
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

        $context->set('host_config', $host_config);

        $bag = new ScriptDataBag();
        $bag->setContext($context)
            ->setRootFolder($root_folder)
            ->setVariables($variables)
            ->setCallbacks($callbacks)
            ->setEnvironment($environment)
            ->setCommands($commands)
            ->setCleanupCommands($context->get(self::SCRIPT_CLEANUP, []));

        $result = $this->runScriptImpl($bag);

        $context->setResult('exitCode', $result ? $result->getExitCode() : 0);
        $context->setResult('commandResult', $result);
    }

    /**
     * @param \Phabalicious\Method\ScriptDataBag $bag
     *
     * @return CommandResult|null
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\ValidationFailedException
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    private function runScriptImpl(ScriptDataBag $bag) : ?CommandResult
    {
        $bag->getContext()->set('break_on_first_error', $this->getBreakOnFirstError());

        $shell = $bag->getShell();
        $execution_context = new ScriptExecutionContext(
            $bag->getRootFolder(),
            $bag->getContext()->get(self::SCRIPT_CONTEXT, ScriptExecutionContext::HOST),
            $bag->getContext()->get(self::SCRIPT_CONTEXT_DATA, [])
        );

        $shell = $execution_context->enter($shell);
        $prev_shell = $bag->getContext()->getShell();
        $bag->getContext()->setShell($shell);
        $shell->setOutput($bag->getContext()->getOutput());

        $shell->pushWorkingDir($execution_context->getScriptWorkingDir());
        $shell->applyEnvironment($bag->getEnvironment());

        $command_result = $this->runCommands($shell, $bag->getCommands(), $bag);

        if (!empty($bag->getCleanupCommands())) {
            $cleanup_result = $this->runCommands($shell, $bag->getCleanupCommands(), $bag);
            if ($cleanup_result->failed()) {
                $this->logger->error(implode("\n", $cleanup_result->getOutput()));
            }
        }

        $shell->popWorkingDir();
        $execution_context->exit();
        $bag->getContext()->setShell($prev_shell);

        return $command_result;
    }

    /**
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    private function runCommands(ShellProviderInterface $shell, array $commands, ScriptDataBag $bag): CommandResult
    {
        $command_result = new CommandResult(0, []);
        foreach ($commands as $line) {
            if (empty($line)) {
                continue;
            }
            if (!is_string($line)) {
                throw new \InvalidArgumentException("Not a valid script block!\n\n" . Yaml::dump($commands, 4));
            }

            $line = $bag->applyReplacements($line);

            if (empty($line)) {
                continue;
            }
            $result = Utilities::extractCallback($line);
            $callback_handled = false;
            if ($result) {
                [$callback_name, $args] = $result;
                $callback_handled = $this->executeCallback(
                    $bag->getContext(),
                    $bag->getCallbacks(),
                    $callback_name,
                    $args
                );
            }
            if (!$callback_handled) {
                $command_result = $shell->run($line, false, false);
                $bag->getContext()->setCommandResult($command_result);

                if ($command_result->failed() && $this->getBreakOnFirstError()) {
                    return $command_result;
                }
            }
        }

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
        $script_context = $script_data['context'] ?? ScriptExecutionContext::HOST;
        $script_questions = $script_data['questions'] ?? [];
        $computed_values = $script_data['computedValues'] ?? [];
        $script_cleanup= $script_data['finally'] ?? [];
        $script_context_data = $script_data;
        if (!empty($script_data['script'])) {
            $script_data = $script_data['script'];
        } else {
            $script_context_data = [];
        }

        $context->set(ScriptMethod::SCRIPT_DATA, $script_data);
        $context->set(ScriptMethod::SCRIPT_CONTEXT, $script_context);
        $context->set(ScriptMethod::SCRIPT_CONTEXT_DATA, $script_context_data);
        $context->set(ScriptMethod::SCRIPT_QUESTIONS, $script_questions);
        $context->set(ScriptMethod::SCRIPT_COMPUTED_VALUES, $computed_values);
        $context->set(ScriptMethod::SCRIPT_CLEANUP, $script_cleanup);
    }
}
