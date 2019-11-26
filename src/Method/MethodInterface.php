<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface MethodInterface
{

    public function getName(): string;

    public function getOverriddenMethod();

    public function supports(string $method_name): bool;

    public function getKeysForDisallowingDeepMerge(): array;

    public function getGlobalSettings(): array;

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array;
    
    public function validateGlobalSettings(array $settings, ValidationErrorBagInterface $errors);

    public function validateConfig(array $config, ValidationErrorBagInterface $errors);

    public function alterConfig(ConfigurationService $configuration_service, array &$data);

    public function createShellProvider(array $host_config);

    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context);

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context);

    public function fallback(string $task, HostConfig $config, TaskContextInterface $context);
}
