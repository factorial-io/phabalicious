<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class NpmMethod extends BaseMethod
{

    public function getName(): string
    {
        return 'node';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    public function getGlobalSettings(): array
    {
        return [
            'executables' => [
                'npm' => 'npm',
                'yarn' => 'yarn',
            ],
        ];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'npmRootFolder' => isset($host_config['gitRootFolder'])
                ? $host_config['gitRootFolder']
                : $host_config['rootFolder'],
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKey('npmRootFolder', 'npmRootFolder should point to your composer root folder.');
        $validation->checkForValidFolderName('npmRootFolder');
    }

    private function runCommand(HostConfig $host_config, TaskContextInterface $context, string $command)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);
        $pwd = $shell->getWorkingDir();
        $shell->cd($host_config['composerRootFolder']);
        $result = $shell->run('#!composer ' . $command);
        $shell->cd($pwd);
        $context->setResult('exitCode', $result->getExitCode());
    }

    private function prepareCommand(HostConfig $host_config, string $command)
    {
        if (!in_array($host_config['type'], array('dev', 'test'))) {
            $command .= ' --no-dev --optimize-autoloader';
        }
        return $command;
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    public function resetPrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = 'install ';
        $command = $this->prepareCommand($host_config, $command);
        $this->runCommand($host_config, $context, $command);
    }

    public function installPrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->resetPrepare($host_config, $context);
    }

    public function composer(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');
        $this->runCommand($host_config, $context, $command);
    }

    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }

        if ($current_stage == 'installDependencies') {
            $this->resetPrepare($host_config, $context);
        }
    }

    public function appUpdate(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runCommand(
            $host_config,
            $context,
            $this->prepareCommand($host_config, 'update')
        );
    }
}
