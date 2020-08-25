<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Utilities\EnsureKnownHosts;
use Phabalicious\Validation\ValidationErrorBagInterface;

class SshMethod extends BaseMethod implements MethodInterface
{

    protected $knownHostsChecked = [];

    public function getName(): string
    {
        return 'ssh';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'ssh';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        // Implementation found in SShSellProvider.
        return [
            'shellProvider' => SshShellProvider::PROVIDER_NAME,
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        // Implementation found in SShSellProvider.
    }

    public function createShellProvider(array $host_config)
    {
        return ShellProviderFactory::create(SshShellProvider::PROVIDER_NAME, $this->logger);
    }

    /**
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     * @throws FailedShellCommandException
     */
    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::preflightTask($task, $config, $context);

        if (empty($this->knownHostsChecked[$config->get('configName')])
          && $config->isMethodSupported($this)
            && !in_array($task, ['about'])
        ) {
            $this->knownHostsChecked[$config->get('configName')] = true;
            $known_hosts = $this->getKnownHosts($config, $context);
            if ($config['host'] !== 'localhost' && !empty($config['disableknownHosts'])) {
                $known_hosts[] = $config['host'] . ':' . $config->get('port', 22);
                EnsureKnownHosts::ensureKnownHosts($context->getConfigurationService(), $known_hosts);
            }
        }
    }

    public function shell(HostConfig $config, TaskContextInterface $context)
    {

        if (!empty($config['sshTunnel'])) {
            $tunnel = $config['sshTunnel'];
            $ssh_command = [
                'ssh',
                '-A',
                '-J',
                sprintf(
                    '%s@%s:%s',
                    $tunnel['bridgeUser'],
                    $tunnel['bridgeHost'],
                    $tunnel['bridgePort']
                ),
                '-p',
                $tunnel['destPort'],
                sprintf(
                    '%s@%s',
                    $config['user'],
                    $tunnel['destHost']
                )
            ];

            $context->setResult('ssh_command', $ssh_command);
        }
    }
}
