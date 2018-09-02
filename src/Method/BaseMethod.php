<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\ValidationErrorBagInterface;
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

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
    }

    public function getGlobalSettings(): array
    {
        return [];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [];
    }

    public function preflightTask(string $task, array $config, TaskContextInterface $context)
    {
        $this->logger->debug('preflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function postflightTask(string $task, array $config, TaskContextInterface $context)
    {
        $this->logger->debug('postflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function fallback(string $task, array $config, TaskContextInterface $context) {
        $this->logger->debug('fallback ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

}