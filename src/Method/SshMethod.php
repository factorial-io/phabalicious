<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class SshMethod extends BaseMethod implements MethodInterface
{

    private $cachedSshPorts = [];

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

        $result = [
            'port' => 22,
            'disableKnownHosts' => $configuration_service->getSetting('disableKnownHosts', false),
        ];

        if (isset($host_config['sshTunnel'])) {
            if (!empty($host_config['port'])) {
                $result['sshTunnel']['localPort'] = $host_config['port'];
            } else {
                if (!empty($this->cachedSshPorts[$host_config['configName']])) {
                    $port = $this->cachedSshPorts[$host_config['configName']];
                } else {
                    $port = rand(1025, 65535);
                }
                $this->cachedSshPorts[$host_config['configName']] = $port;
                $result['port'] = $port;
                $result['sshTunnel']['localPort'] = $port;
            }

            if (isset($host_config['docker']['name'])) {
                $result['sshTunnel']['destHostFromDockerContainer'] = $host_config['docker']['name'];
            }
        }

        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys([
            'host' => 'Hostname to connect to',
            'port' => 'The port to connect to',
            'user' => 'Username to use for this connection',
        ]);

        if (!empty($config['sshTunnel'])) {
            $tunnel_validation = new ValidationService($config['sshTunnel'], $errors, 'sshTunnel-config');
            $tunnel_validation->hasKeys([
                'bridgeHost' => 'The hostname of the bridge-host',
                'bridgeUser' => 'The username to use to connect to the bridge-host',
                'bridgePort' => 'The port to use to connect to the bridge-host',
                'destHost' => 'The hostname of the destination host',
                'destPort' => 'The port of the destination host',
                'localPort' => 'The local port to forward to the destination-host'
            ]);
        }
        if (isset($config['strictHostKeyChecking'])) {
            $errors->addWarning('strictHostKeyChecking', 'Please use `disableKnownHosts` instead.');
        }
    }

    public function createShellProvider(array $host_config)
    {
        return new SshShellProvider($this->logger);
    }
}