<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationService;

class SshShellProvider extends LocalShellProvider
{
    const PROVIDER_NAME = 'ssh';

    static protected $cachedSshPorts = [];

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result =  parent::getDefaultConfig($configuration_service, $host_config);
        $result['shellExecutable'] = '/usr/bin/ssh';
        $result['disableKnownHosts'] = $configuration_service->getSetting('disableKnownHosts', false);
        $result['port'] = 22;

        if (isset($host_config['sshTunnel'])) {
            if (!empty($host_config['port'])) {
                $result['sshTunnel']['localPort'] = $host_config['port'];
            } elseif (!empty($host_config['configName'])) {
                if (!empty(self::$cachedSshPorts[$host_config['configName']])) {
                    $port = self::$cachedSshPorts[$host_config['configName']];
                } else {
                    $port = rand(1025, 65535);
                }
                self::$cachedSshPorts[$host_config['configName']] = $port;
                $result['port'] = $port;
                $result['sshTunnel']['localPort'] = $port;
            }

            if (isset($host_config['docker']['name'])) {
                $result['sshTunnel']['destHostFromDockerContainer'] = $host_config['docker']['name'];
            }
        }

        return $result;
    }

    public function validateConfig(array $config, \Phabalicious\Validation\ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

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
                'destPort' => 'The port of the destination host',
                'localPort' => 'The local port to forward to the destination-host'
            ]);
            if (empty($config['sshTunnel']['destHostFromDockerContainer'])) {
                $tunnel_validation->hasKey('destHost', 'The hostname of the destination host');
            }
        }
        if (isset($config['strictHostKeyChecking'])) {
            $errors->addWarning('strictHostKeyChecking', 'Please use `disableKnownHosts` instead.');
        }
    }

    protected function addCommandOptions(&$command)
    {
        if ($this->hostConfig['disableKnownHosts']) {
            $command[] = '-o';
            $command[] = 'StrictHostKeyChecking=no';
            $command[] = '-o';
            $command[] = 'UserKnownHostsFile=/dev/null';
        }
    }

    protected function getShellCommand()
    {
        $command = [
            $this->hostConfig['shellExecutable'],
            '-A',
            '-p',
            $this->hostConfig['port'],
            ];
        $this->addCommandOptions($command);
        $command[] = $this->hostConfig['user'] . '@' . $this->hostConfig['host'];
        $command[] = '/bin/sh';

        return $command;
    }

    /**
     * @param $dir
     * @return bool
     * @throws \Exception
     */
    public function exists($dir):bool
    {
        $result = $this->run('stat ' . $dir);
        return $result->succeeded();
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context): bool
    {
        $command = [
            '/usr/bin/scp',
            '-P',
            $this->hostConfig['port']
        ];

        $this->addCommandOptions($command);

        $command[] = $source;
        $command[] = $this->hostConfig['user'] . '@' . $this->hostConfig['host'] . ':' . $dest;

        return $this->runCommand($command, $context);
    }


}