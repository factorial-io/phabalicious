<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class GitMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'git';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'git';
    }

    public function getGlobalSettings(): array
    {
        return [
            'gitOptions' =>  [
                'pull' => [
                    '--no-edit',
                    '--rebase'
                ],
            ],
            'executables' => [
                'git' => 'git',
            ],
        ];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'gitRootFolder' => $host_config['rootFolder'],
            'ignoreSubmodules' => false,
            'gitOptions' => $configuration_service->getSetting('gitOptions', []),
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKey('gitRootFolder', 'gitRootFolder should point to your gits root folder.');
    }

    public function getVersion(HostConfig $host_config, TaskContextInterface $context)
    {
        $host_config->shell()->cd($host_config['gitRootFolder']);
        $result = $host_config->shell()->run('#!git describe --always');
        return $result->succeeded() ? $result->getOutput()[0] : '';
    }

    public function getCommitHash(HostConfig $host_config, TaskContextInterface $context)
    {
        $host_config->shell()->cd($host_config['gitRootFolder']);
        $result = $host_config->shell()->run('#!git rev-parse HEAD');
        return $result->getOutput()[0];
    }

    public function isWorkingcopyClean(HostConfig $host_config, TaskContextInterface $context)
    {
        $host_config->shell()->cd($host_config['gitRootFolder']);
        $result = $host_config->shell()->run('#!git diff --exit-code --quiet');
        return $result->succeeded();
    }

    public function version(HostConfig $host_config, TaskContextInterface $context)
    {
        $context->set('version', $this->getVersion($host_config, $context));
    }

}