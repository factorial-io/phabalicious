<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class DockerExecShellProvider extends LocalShellProvider implements ShellProviderInterface
{
    const PROVIDER_NAME = 'docker-exec';

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result =  parent::getDefaultConfig($configuration_service, $host_config);
        $result['shellExecutable'] = 'docker';

        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys([
            'docker' => 'The docker-configuration to use',
        ]);
        if (!$errors->hasErrors()) {
            $validation = new ValidationService($config['docker'], $errors, 'host:docker');
            $validation->hasKey('name', 'The name of the docker-container to use');
        }
    }


    protected function getShellCommand($options = [])
    {
        $command = [
            $this->hostConfig['shellExecutable'],
            'exec',
            (empty($options['tty']) ? '-i' : '-it'),
            $this->hostConfig['docker']['name'],
        ];
        if (!empty($options['tty'])) {
            $command[] = '/bin/sh';
        }


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

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $command = [
            'docker',
            'cp',
            $source,
            $this->hostConfig['docker']['name'] . ':' . $dest
        ];

        return $this->runCommand($command, $context, false, true);
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $command = [
            'docker',
            'cp',
            $this->hostConfig['docker']['name'] . ':' . $source,
            $dest,
        ];

        return $this->runCommand($command, $context, false, true);
    }

}