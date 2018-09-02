<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Psr\Log\LoggerInterface;

abstract class BaseMethod implements MethodInterface
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function supports(string $method_name): bool
    {
        return false;
    }

    public function getOverriddenMethod()
    {
        return false;
    }

    public function validateConfig(array $config): bool
    {
        return true;
    }

    public function getGlobalSettings(): array
    {
        return [];
    }

    public function getDefaultConfig(ConfigurationService $configuration): array
    {
        return [];
    }

    public function preflightTask(string $task, array $config, TaskContextInterface $context)
    {
        $this->logger->debug('preflightTask ' . $task, [$config, $context]);
    }

    public function postflightTask(string $task, array $config, TaskContextInterface $context)
    {
        $this->logger->debug('postflightTask ' . $task, [$config, $context]);
    }

    public function fallback(string $task, array $config, TaskContextInterface $context) {
        // TODO: Implement fallback() method.
    }

}