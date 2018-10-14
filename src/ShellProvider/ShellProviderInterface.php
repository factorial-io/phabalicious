<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ScopedLogLevel\LogLevelStackGetterInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

interface ShellProviderInterface extends LogLevelStackGetterInterface
{
    public function getName(): string;

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array;

    public function validateConfig(array $config, ValidationErrorBagInterface $errors);

    public function setHostConfig(HostConfig $config);

    public function getHostConfig(): HostConfig;

    public function getWorkingDir(): string;

    public function exists($file): bool;

    public function cd(string $dir): ShellProviderInterface;

    public function run(string $command, $capture_output = false): CommandResult;

    public function applyEnvironment(array $environment);

    public function setOutput(OutputInterface $output);

    public function putFile(string $source, string $dest, TaskContextInterface $context): bool;

    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context
    );

    public function createShellProcess(array $command = []): Process;

    public function createTunnelProcess(HostConfig $target_config);

}