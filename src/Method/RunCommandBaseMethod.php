<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

abstract class RunCommandBaseMethod extends BaseMethod implements MethodInterface
{


    abstract protected function getExecutableName() : string;
    abstract protected function getRootFolderKey(): string;

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
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

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            $this->getRootFolderKey() => isset($host_config['gitRootFolder'])
                ? $host_config['gitRootFolder']
                : $host_config['rootFolder'],
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validation = new ValidationService($config, $errors, 'host-config');
        $args = $this->getRootFolderKey();
        $validation->hasKey($args, sprintf('%s should point to your root folder for %s.', $args, $this->getName()));
        $validation->checkForValidFolderName($args);
    }

    protected function runCommand(
        HostConfig $host_config,
        TaskContextInterface $context,
        string $command
    ) {
        $command = $this->prepareCommand($host_config, $context, $command);

        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);
        $shell->pushWorkingDir($host_config[$this->getRootFolderKey()]);
        $result = $shell->run('#!' . $this->getExecutableName(). ' ' . $command);
        $context->setResult('exitCode', $result->getExitCode());
        $shell->popWorkingDir();
    }

    protected function prepareCommand(HostConfig $host_config, TaskContextInterface $context, string $command)
    {
        return $command;
    }
}
