<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;

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

    public function createShellProvider(array $host_config)
    {
    }

    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->debug('preflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->debug('postflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function fallback(string $task, HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->debug('fallback ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function executeCommand(TaskContext $context, $command_name, $args)
    {
        /** @var \Symfony\Component\Console\Command\Command $command */
        $command = $context->getCommand()->getApplication()->find($command_name);
        if (isset($args[0])) {
            $args[$command_name] = $args[0];
            unset($args[0]);
        }
        $args['--config'] = $context->get('host_config')['configName'];
        $input = new ArrayInput($args);
        $command->run($input, $context->getOutput());
    }

}