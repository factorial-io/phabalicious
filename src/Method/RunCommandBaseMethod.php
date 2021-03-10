<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
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

    public function getGlobalSettings(): array
    {
        $executable = $this->getExecutableName();
        return [
            'executables' => [
                $executable => $executable,
            ],
        ];
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

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            $this->getRootFolderKey() => isset($host_config['gitRootFolder'])
                ? $host_config['gitRootFolder']
                : $host_config['rootFolder'],
            $this->getRunContextKey() => self::HOST_CONTEXT,
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
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
            $docker_config = DockerMethod::getDockerConfig($host_config, $context->getConfigurationService());
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

    protected function prepareCommand(HostConfig $host_config, TaskContextInterface $context, string $command)
    {
        return $command;
    }
}
