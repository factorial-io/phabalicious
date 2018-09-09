<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class LocalShellProvider implements ShellProviderInterface
{

    public function getName(): string
    {
        return 'local';
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, $host_config): array
    {
        return [
            'rootFolder' => $configuration_service->getFabfilePath(),
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validator = new ValidationService($config, $errors, 'host-config');
        $validator->hasKey('rootFolder', 'Missing rootFolder, should point to the root of your application');
    }
}