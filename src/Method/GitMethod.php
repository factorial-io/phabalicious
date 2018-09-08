<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Validation\ValidationErrorBagInterface;

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
        ];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'gitRootFolder' => $host_config['rootFolder'],
            'ignoreSubmodules' => false,
            'gitOptions' => $configuration_service->getSetting('gitOptions', [])
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {

    }
}