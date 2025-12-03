<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class ScottyShellProvider extends LocalShellProvider
{
    public const PROVIDER_NAME = 'scotty';

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $result = [
            'shellExecutable' => '/bin/sh',
        ];

        return $parent->merge(new Node($result, $this->getName().' shellprovider defaults'));
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors): void
    {
        parent::validateConfig($config, $errors);

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys([
            'scotty' => 'The scotty configuration to use',
        ]);

        if (!$errors->hasErrors()) {
            $scotty_validation = new ValidationService(
                $config['scotty'],
                $errors,
                'host:scotty'
            );
            $scotty_validation->hasKey(
                'shellService',
                'The service name to connect to for shell access'
            );
        }
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        $command = [
            $this->hostConfig['executables']['scottyctl'] ?? 'scottyctl',
        ];

        // Add global options before subcommand
        $command[] = '--server';
        $command[] = $this->hostConfig['scotty']['server'];

        if (!empty($this->hostConfig['scotty']['access-token'])) {
            $command[] = '--access-token';
            $command[] = $this->hostConfig['scotty']['access-token'];
        }

        // Add subcommand
        $command[] = 'app:shell';

        // Add app name and service
        $command[] = $this->hostConfig['scotty']['app-name'];
        $command[] = $this->hostConfig['scotty']['shellService'];

        // For interactive shells with TTY
        if ($options->useTty() && !$options->isShellExecutableProvided()) {
            $command[] = '--shell';
            $command[] = $this->hostConfig['shellExecutable'];
        }

        // Add any program to call
        if (count($program_to_call)) {
            $command[] = '--command';
            $command[] = implode(' ', $program_to_call);
        }

        return $command;
    }

    public function exists($file): bool
    {
        return $this->run(sprintf('stat %s > /dev/null 2>&1', $file), RunOptions::HIDE_OUTPUT, false)
            ->succeeded();
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException('File operations are not yet supported by scottyctl. Use local shell provider for file operations.');
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException('File operations are not yet supported by scottyctl. Use local shell provider for file operations.');
    }
}
