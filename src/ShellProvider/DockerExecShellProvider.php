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


    protected function getShellCommand()
    {
        $command = [
            $this->hostConfig['shellExecutable'],
            'exec',
            '-i',
            $this->hostConfig['docker']['name'],
        ];

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
        // @todo implement logic.
        return false;
    }

}