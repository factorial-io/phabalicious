<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

abstract class RunCommandBaseMethod extends BaseMethod implements MethodInterface
{


    const HOST_CONTEXT = 'host';
    const DOCKER_HOST_CONTEXT = 'dockerHost';

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    protected function getExecutableName() : string
    {
        return $this->getName();
    }

    protected function getConfigPrefix() : string
    {
        return $this->getName();
    }

    public function getRootFolderKey(): string
    {
        return $this->getConfigPrefix() . 'RootFolder';
    }

    protected function getRunContextKey()
    {
        return "{$this->getConfigPrefix()}RunContext";
    }

    public function getGlobalSettings(): Node
    {
        $executable = $this->getExecutableName();
        return new Node([
            'executables' => [
                $executable => $executable,
            ],
        ], $this->getName() . ' global settings');
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        if (parent::isRunningAppRequired($host_config, $context, $task)) {
            return true;
        }
        if ($task !== $this->getName()) {
            return false;
        }
        return $host_config[$this->getRunContextKey()] === self::HOST_CONTEXT;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        return new Node([
            $this->getRootFolderKey() => $host_config['gitRootFolder'] ?? $host_config['rootFolder'],
            $this->getRunContextKey() => self::HOST_CONTEXT,
        ], $this->getName() . ' defaults');
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors)
    {
        $validation = new ValidationService($config, $errors, 'host-config');
        $args = $this->getRootFolderKey();
        $validation->hasKey($args, sprintf('%s should point to your root folder for %s.', $args, $this->getName()));
        $validation->checkForValidFolderName($args);

        $runContextKey = $this->getRunContextKey();
        $validation->isOneOf($runContextKey, [self::HOST_CONTEXT, self::DOCKER_HOST_CONTEXT]);

        if ($config[$runContextKey] == self::DOCKER_HOST_CONTEXT && !in_array('docker', $config['needs'])) {
            $errors->addError($runContextKey, sprintf(
                '`%s` is set to `%s`, this requires `docker` as part of the hosts needs.',
                $runContextKey,
                self::DOCKER_HOST_CONTEXT
            ));
        }
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\ValidationFailedException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     */
    protected function runCommand(
        HostConfig $host_config,
        TaskContextInterface $context,
        string $command
    ) {
        $command = $this->prepareCommand($host_config, $context, $command);

        /** @var ShellProviderInterface $shell */
        if ($host_config[$this->getRunContextKey()] == self::DOCKER_HOST_CONTEXT) {
            /** @var DockerMethod $docker_method */
            $docker_method = $context->getConfigurationService()->getMethodFactory()->getMethod('docker');
            $docker_config = $docker_method->getDockerConfig($host_config, $context);
            $shell = $docker_config->shell();
            $shell->pushWorkingDir($docker_method->getProjectFolder($docker_config, $host_config));
            $shell->cd($host_config[$this->getRootFolderKey()]);
        } else {
            $shell = $this->getShell($host_config, $context);
            $shell->pushWorkingDir($host_config[$this->getRootFolderKey()]);
        }

        $result = $shell->run('#!' . $this->getExecutableName(). ' ' . $command);
        $context->setResult('exitCode', $result->getExitCode());
        $shell->popWorkingDir();
    }

    protected function prepareCommand(HostConfig $host_config, TaskContextInterface $context, string $command): string
    {
        return $command;
    }
}
