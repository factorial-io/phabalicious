<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Validation\ValidationErrorBagInterface;

class SshMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'ssh';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'ssh';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        // Implementation found in SShSellProvider.
        return [
            'shellProvider' => SshShellProvider::PROVIDER_NAME,
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        // Implementation found in SShSellProvider.
    }

    public function createShellProvider(array $host_config)
    {
        return ShellProviderFactory::create(SshShellProvider::PROVIDER_NAME, $this->logger);
    }

}