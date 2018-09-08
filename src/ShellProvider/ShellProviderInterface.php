<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface ShellProviderInterface
{
    public function getName(): string;

    public function getGlobalSettings(): array;

    public function getDefaultConfig(ConfigurationService $configuration_service, $host_config): array;

    public function validateConfig(array $config, ValidationErrorBagInterface $errors);

}