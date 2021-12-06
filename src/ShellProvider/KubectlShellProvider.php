<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class KubectlShellProvider extends LocalShellProvider implements ShellProviderInterface
{
    const PROVIDER_NAME = 'kubectl';

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setPreventTimeout(true);
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result =  parent::getDefaultConfig($configuration_service, $host_config);
        $result['kubectlExecutable'] = 'kubectl';
        $result['kubectlOptions'] = [];
        $result['shellExecutable'] = '/bin/sh';

        $result['kube']['namespace'] = 'default';
        $result['kube']['podSelector'] = [
            'service_name=%host.kube.serviceName%',
        ];
        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys(['kube' => 'The kubernetes config to use']);
        $validation->isArray('kubectlOptions', 'A set of key value pairs to pass as options to kubectl');

        if (!$errors->hasErrors()) {
            $validation = new ValidationService($config['kube'], $errors, 'host:kube');
            $validation->isArray('podSelector', 'A set of selectors to get the pod you want to connect to.');
            $validation->hasKey('namespace', 'The namespace the pod is located in.');
        }
    }

    public function createShellProcess(array $command = [], ShellOptions $options = null): Process
    {
        // Apply kubectl environment vars.
        $this->setShellEnvironmentVars($this->hostConfig['kube']['environment']);
        return parent::createShellProcess($command, $options);
    }

    public static function getKubectlCmd(array $config, $kubectl_cmd = '#!kubectl', $exclude = [])
    {
        $cmd = [ $kubectl_cmd ];
        if (!empty($config['kubectlOptions'])) {
            foreach ($config['kubectlOptions'] as $k => $v) {
                $cmd[] = $k;
                if ($v !== "") {
                    $cmd[] = $v;
                }
            }
        }

        foreach (array('kubeconfig', 'namespace', 'context') as $key) {
            if (!empty($config['kube'][$key]) && !in_array($key, $exclude)) {
                $cmd[] = '--' . $key;
                $cmd[] = $config['kube'][$key];
            }
        }

        return $cmd;
    }
    protected function getKubeCmd()
    {
        return self::getKubectlCmd($this->getHostConfig()->raw(), 'kubectl');
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        if (empty($this->hostConfig['kube']['podForCli'])) {
            throw new \RuntimeException("Could not get shell, as podForCli is empty!");
        }
        $command = $this->getKubeCmd();
        $command[] = 'exec';
        $command[] = ($options->useTty() ? '-it' : '-i');
        $command[] = $this->hostConfig['kube']['podForCli'];
        $command[] = '--';

        if ($options->useTty() && !$options->isShellExecutableProvided()) {
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
        if ($this->hostConfig->getProperty('kube.useRsync')) {
            return $this->getRsyncFileCommand($source, 'rsync:' . $dest);
        }
        $command = $this->getKubeCmd();
        $command[] = 'cp';
        $command[] = trim($source);
        $command[] = $this->hostConfig['kube']['podForCli'] . ':' . trim($dest);

        return $command;
    }

    /**
     * @param string $source
     * @param string $dest
     * @return string[]
     */
    public function getGetFileCommand(string $source, string $dest): array
    {

        if ($this->hostConfig->getProperty('kube.useRsync')) {
            return $this->getRsyncFileCommand('rsync:' . $source, $dest);
        }
        $command = $this->getKubeCmd();
        $command[] = 'cp';
        $command[] = $this->hostConfig['kube']['podForCli'] . ':' . trim($source);
        $command[] = trim($dest);

        return $command;
    }

    protected function getRsyncFileCommand(string $source, string $dest): array
    {
        $kubectl_command = $this->getKubeCmd();
        $kubectl_command[] = 'exec';
        $kubectl_command[] = $this->hostConfig['kube']['podForCli'];
        $kubectl_command[] = '-i';
        $kubectl_command[] = '--';

        $command = [];
        $command[] = 'rsync';
        $command[] = '-uP';
        $command[] = '--blocking-io';
        $command[] = '--rsync-path=';
        $command[] = sprintf('--rsh=%s', implode(' ', $kubectl_command));
        $command[] = trim($source);
        $command[] = trim($dest);

        return $command;
    }

    /**
     * @param string $ip
     * @param int $port
     * @param string $public_ip
     * @param int $public_port
     * @param HostConfig $config
     * @param TaskContextInterface $context
     *
     * @return bool
     */
    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context
    ) {
        $command = $this->getKubeCmd();
        $command[] = 'port-forward';
        $command[] = sprintf('pod/%s', $config['kube']['podForCli']);
        $command[] = sprintf('%d:%d', $public_port, $port);

        return $this->runProcess($command, $context, true, true);
    }
}
