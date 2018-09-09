<?php

namespace Phabalicious\ShellProvider;

use Foo\DataProviderIssue2922\SecondHelloWorldTest;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface ShellProviderInterface
{
    public function getName(): string;

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array;

    public function validateConfig(array $config, ValidationErrorBagInterface $errors);

    public function setHostConfig(HostConfig $config);

    public function getHostConfig(): HostConfig;

    public function getWorkingDir(): string;

    public function cd(string $dir): ShellProviderInterface;

    public function run(string $command, $capture_output = false): CommandResult;

}