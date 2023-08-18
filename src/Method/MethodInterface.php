<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\TunnelHelper\TunnelHelperFactory;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface MethodInterface
{

    public function getName(): string;

    public function getOverriddenMethod();

    public function supports(string $method_name): bool;

    public function getKeysForDisallowingDeepMerge(): array;

    public function getGlobalSettings(ConfigurationService $configuration): Node;

    public function setTunnelHelperFactory(TunnelHelperFactory  $tunnel_helper_factory);

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node;

    public function validateGlobalSettings(Node $settings, ValidationErrorBagInterface $errors);

    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors
    );

    public function alterConfig(ConfigurationService $configuration_service, Node $data);

    public function createShellProvider(array $host_config);

    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context);

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context);

    public function fallback(string $task, HostConfig $config, TaskContextInterface $context);

    public function getRootFolderKey(): string;

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task);

    public function getMethodDependencies(MethodFactory $factory, \ArrayAccess $data): array;

    public function getDeprecationMapping(): array;

    /**
     * @return \Phabalicious\ConfigurationService\DeprecatedValueMapping[]
     */
    public function getDeprecatedValuesMapping(): array;
}
