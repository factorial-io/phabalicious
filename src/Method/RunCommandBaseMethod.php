<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ConfigurationService\DeprecatedValueMapping;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

abstract class RunCommandBaseMethod extends BaseMethod implements MethodInterface
{
    public const HOST_CONTEXT = 'host';
    public const DOCKER_HOST_CONTEXT = 'docker-host';
    public const DOCKER_IMAGE_CONTEXT = ScriptExecutionContext::DOCKER_IMAGE;
    public const DOCKER_IMAGE_ON_DOCKER_HOST_CONTEXT = 'docker-image-on-docker-host';

    public const RUN_CONTEXT_KEY = 'context';
    public const ROOT_FOLDER_KEY = 'rootFolder';

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    protected function getExecutableName(): string
    {
        return $this->getName();
    }

    protected function getConfigPrefix(): string
    {
        return $this->getName();
    }

    public function getRootFolderKey(): string
    {
        return $this->getConfigPrefix().'.rootFolder';
    }

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        $executable = $this->getExecutableName();

        return new Node([
            'executables' => [
                $executable => $executable,
            ],
        ], $this->getName().' global settings');
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        if (parent::isRunningAppRequired($host_config, $context, $task)) {
            return true;
        }
        if ($task !== $this->getName()) {
            return false;
        }

        return self::HOST_CONTEXT === $host_config->getProperty($this->getConfigKey(self::RUN_CONTEXT_KEY));
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        return new Node([
            $this->getConfigPrefix() => [
                'rootFolder' => $host_config['gitRootFolder'] ?? $host_config['rootFolder'],
                'context' => self::HOST_CONTEXT,
            ],
        ], $this->getName().' method defaults');
    }

    public function getDeprecationMapping(): array
    {
        $mapping = parent::getDeprecationMapping();
        $prefix = $this->getConfigPrefix();

        return array_merge($mapping, [
            "{$prefix}RootFolder" => "{$prefix}.rootFolder",
            "{$prefix}RunContext" => "{$prefix}.context",
        ]);
    }

    public function getDeprecatedValuesMapping(): array
    {
        $mapping = parent::getDeprecatedValuesMapping();
        $prefix = $this->getConfigPrefix();

        return array_merge($mapping, [
            new DeprecatedValueMapping("{$prefix}RunContext", 'dockerHost', 'docker-host'),
            new DeprecatedValueMapping("{$prefix}.context", 'dockerHost', 'docker-host'),
        ]);
    }

    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors,
    ): void {
        $validation = new ValidationService($config, $errors, 'host-config');
        $prefix = $this->getConfigPrefix();
        $validation->deprecate([
            "{$prefix}RootFolder" => "please change to `{$prefix}.rootFolder`",
            "{$prefix}RunContext" => "please change to `{$prefix}.context`",
        ]);
        $args = $this->getRootFolderKey();
        $validation->hasKey($args, sprintf('%s should point to your root folder for %s.', $args, $this->getName()));
        $validation->checkForValidFolderName($args);

        $run_context_key = $this->getConfigKey(self::RUN_CONTEXT_KEY);
        $validation->isOneOf(
            $run_context_key,
            [
                self::HOST_CONTEXT,
                self::DOCKER_HOST_CONTEXT,
                self::DOCKER_IMAGE_CONTEXT,
                self::DOCKER_IMAGE_ON_DOCKER_HOST_CONTEXT,
            ]
        );

        if (self::DOCKER_HOST_CONTEXT == $config->getProperty($run_context_key)
        && !in_array('docker', $config['needs'])
        ) {
            $errors->addError($run_context_key, sprintf(
                '`%s` is set to `%s`, this requires `docker` as part of hosts.%s.needs',
                $run_context_key,
                self::DOCKER_HOST_CONTEXT,
                $config->get('configName')->getValue()
            ));
        }
    }

    /**
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function runCommand(
        HostConfig $host_config,
        TaskContextInterface $context,
        string $command,
    ) {
        $command = $this->prepareCommand($host_config, $context, $command);
        $run_context = $host_config->getProperty($this->getConfigKey(self::RUN_CONTEXT_KEY));

        // Lets construct a script and set the execution context there.
        /** @var ScriptMethod $script_method */
        $script_method = $context->getConfigurationService()->getMethodFactory()->getMethod('script');
        $script_context = clone $context;
        $script_context->set(
            ScriptMethod::SCRIPT_CONTEXT_DATA,
            $host_config->getProperty($this->getConfigPrefix())
        );

        switch ($run_context) {
            case self::DOCKER_IMAGE_CONTEXT:
                $script_context->set(ScriptMethod::SCRIPT_CONTEXT, $run_context);

                $shell = $this->getShell($host_config, $context);
                $shell->pushWorkingDir($this->getConfig($host_config, self::ROOT_FOLDER_KEY));

                break;

            case self::DOCKER_HOST_CONTEXT:
            case self::DOCKER_IMAGE_ON_DOCKER_HOST_CONTEXT:
                /** @var DockerMethod $docker_method */
                $docker_method = $context->getConfigurationService()->getMethodFactory()->getMethod('docker');
                $docker_config = $docker_method->getDockerConfig($host_config, $context);
                $shell = $docker_config->shell();
                $shell->pushWorkingDir($docker_method->getProjectFolder($docker_config, $host_config));
                $shell->cd($this->getConfig($host_config, self::ROOT_FOLDER_KEY));

                if (self::DOCKER_IMAGE_ON_DOCKER_HOST_CONTEXT == $run_context) {
                    $script_context->set(ScriptMethod::SCRIPT_CONTEXT, ScriptExecutionContext::DOCKER_IMAGE);
                }

                break;

            default:
                $shell = $this->getShell($host_config, $context);
                $shell->pushWorkingDir($this->getConfig($host_config, self::ROOT_FOLDER_KEY));

                break;
        }

        $commands = [
            sprintf('#!%s %s', $this->getExecutableName(), $command),
        ];

        $script_context->setShell($shell);

        $variables = Utilities::buildVariablesFrom($host_config, $context);
        $bag = new ScriptDataBag();
        $bag->setContext($script_context)
            ->setVariables($variables)
            ->setCommands($commands)
            ->setRootFolder($this->getConfig($host_config, self::ROOT_FOLDER_KEY));

        $result = $script_method->runScriptImpl($bag);
        $context->mergeResults($script_context);

        $context->setResult('exitCode', $result->getExitCode());
        $context->setCommandResult($result);

        $shell->popWorkingDir();
        if ($result && $result->failed()) {
            $result->throwException(sprintf('Command `%s` failed!', implode(' ', $commands)));
        }
    }

    protected function prepareCommand(HostConfig $host_config, TaskContextInterface $context, string $command): string
    {
        return $command;
    }

    private function getConfigKey(string $key): string
    {
        return sprintf('%s.%s', $this->getConfigPrefix(), $key);
    }

    protected function getConfig(HostConfig $host_config, string $key)
    {
        return $host_config->getProperty($this->getConfigKey($key));
    }
}
