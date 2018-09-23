<?php

namespace Phabalicious\ShellProvider;

class SshShellProvider extends LocalShellProvider
{

    public function getDefaultConfig(\Phabalicious\Configuration\ConfigurationService $configuration_service, array $host_config): array
    {
        $config =  parent::getDefaultConfig($configuration_service, $host_config);
        $config['shellExecutable'] = '/usr/bin/ssh';
        $config['disableKnownHosts'] = false;
        $config['port'] = 22;

        return $config;
    }

    public function validateConfig(array $config, \Phabalicious\Validation\ValidationErrorBagInterface $errors)
    {
        // TODO
        parent::validateConfig($config, $errors);
    }

    protected function getShellCommand()
    {
        $command = [
            $this->hostConfig['shellExecutable'],
            '-A',
            '-p',
            $this->hostConfig['port'],
            ];
        if ($this->hostConfig['disableKnownHosts']) {
            $command[] = '-o';
            $command[] = 'StrictHostKeyChecking=no';
            $command[] = '-o';
            $command[] = 'UserKnownHostsFile=/dev/null';
        }
        $command[] = $this->hostConfig['user'] . '@' . $this->hostConfig['host'];
        $command[] = '/bin/sh';

        return $command;

    }
}