<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;

abstract class BaseShellProvider implements ShellProviderInterface
{

    /** @var HostConfig */
    protected $hostConfig;

    /** @var string */
    private $workingDir;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'rootFolder' => $configuration_service->getFabfilePath(),
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validator = new ValidationService($config, $errors, 'host-config');
        $validator->hasKey('rootFolder', 'Missing rootFolder, should point to the root of your application');
    }

    public function setHostConfig(HostConfig $config)
    {
        $this->hostConfig = $config;
        $this->workingDir = $config['rootFolder'];
    }

    public function getHostConfig(): HostConfig
    {
        return $this->getHostConfig();
    }

    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    public function cd(string $dir): ShellProviderInterface
    {
        $this->workingDir = $dir;
        $this->logger->debug('New working dir: ' . $dir);

        return $this;
    }


}