<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

interface MethodInterface
{

    public function getName(): string;

    public function getOverriddenMethod();

    public function supports(string $method_name): bool;

    public function validateConfig(array $config): bool;

    public function getGlobalSettings(): array;

    public function getDefaultConfig(ConfigurationService $configuration): array;

    public function preflightTask(string $task, array $config, TaskContextInterface $context);

    public function postflightTask(string $task, array $config, TaskContextInterface $context);

    public function fallback(string $task, array $config, TaskContextInterface $context);

}