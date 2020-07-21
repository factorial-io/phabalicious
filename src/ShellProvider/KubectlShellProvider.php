<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class KubectlShellProvider extends LocalShellProvider implements ShellProviderInterface
{
    const PROVIDER_NAME = 'kubectl';

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result =  parent::getDefaultConfig($configuration_service, $host_config);
        $result['kubectlExecutable'] = 'kubectl';
        $result['shellExecutable'] = '/bin/sh';

        $result['kube']['namespace'] = 'default';
        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys([
            'kube' => 'The kubernetes config to use',
        ]);
        if (!$errors->hasErrors()) {
            $validation = new ValidationService($config['kube'], $errors, 'host:kube');
            $validation->isArray('podSelector', 'A set of selectors to get the pod you want to connect to.');
            $validation->hasKey('namespace', 'The namespace the pod is located in.');
        }
    }


    public function getShellCommand(array $program_to_call, array $options = []): array
    {
        if (empty($this->hostConfig['kube']['podForCli'])) {
            throw new \RuntimeException("Could not get shell, as podForCli is empty!");
        }
        $command = [
            $this->hostConfig['kubectlExecutable'],
            'exec',
            (empty($options['tty']) ? '-i' : '-it'),
            $this->hostConfig['kube']['podForCli'],
            '--namespace',
            $this->hostConfig['kube']['namespace'],
            '--'
        ];
        if (!empty($options['tty']) && empty($options['shell_provided'])) {
            $command[] = $this->hostConfig['shellExecutable'];
        }

        if (count($program_to_call)) {
            foreach ($program_to_call as $p) {
                $command[] = $p;
            }
        }

        return $command;
    }

    /**
     * @param string $dir
     * @return bool
     * @throws \Exception
     */
    public function exists($dir):bool
    {
        $result = $this->run(sprintf('stat %s > /dev/null 2>&1', $dir), false, false);
        return $result->succeeded();
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $command = $this->getPutFileCommand($source, $dest);
        return $this->runProcess($command, $context, false, true);
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $command = $this->getGetFileCommand($source, $dest);
        return $this->runProcess($command, $context, false, true);
    }


    /**
     * {@inheritdoc}
     */
    public function wrapCommandInLoginShell(array $command)
    {
        array_unshift(
            $command,
            '/bin/bash',
            '--login',
            '-c'
        );
        return $command;
    }

    /**
     * @param string $source
     * @param string $dest
     * @return string[]
     */
    public function getPutFileCommand(string $source, string $dest): array
    {
        return [
            'kubectl',
            'cp',
            trim($source),
            $this->hostConfig['kube']['podForCli'] . ':' . trim($dest),
            '--namespace',
            $this->hostConfig['kube']['namespace'],
        ];
    }

    /**
     * @param string $source
     * @param string $dest
     * @return string[]
     */
    public function getGetFileCommand(string $source, string $dest): array
    {
        return [
            'kubectl',
            'cp',
            $this->hostConfig['kube']['podForCli'] . ':' . trim($source),
            trim($dest),
            '--namespace',
            $this->hostConfig['kube']['namespace'],

        ];
    }
}
