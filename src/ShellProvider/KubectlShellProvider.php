<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class KubectlShellProvider extends LocalShellProvider
{
    public const PROVIDER_NAME = 'kubectl';

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setPreventTimeout(true);
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $result = [];
        $result['kubectlExecutable'] = 'kubectl';
        $result['kubectlOptions'] = [];
        $result['shellExecutable'] = '/bin/sh';

        $result['kube']['namespace'] = 'default';
        $result['kube']['podSelector'] = [
            'service_name=%host.kube.serviceName%',
        ];

        return $parent->merge(new Node($result, $this->getName().' shellprovider defaults'));
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors): void
    {
        parent::validateConfig($config, $errors);

        if (!$config->has('kubectlVersion')) {
            $version = $this->getKubectlClientVersion(
                $config,
                $config->get('kubectlExecutable', 'kubectl')->getValue()
            );
            $this->logger->info(sprintf('Found kubectl with version %d.%d', $version['major'], $version['minor']));
            $config->set('kubectlVersion', new Node($version, 'kubectl version info'));
        }

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys(['kube' => 'The kubernetes config to use']);
        $validation->isArray('kubectlOptions', 'A set of key value pairs to pass as options to kubectl');

        if ($validation->hasKey('kubectlVersion', 'kubectl version info missing')) {
            $version_validation = new ValidationService($config['kubectlVersion'], $errors, 'host.kubectlVersion');
            $version_validation->hasKeys([
                'minor' => 'minor version number of kubectl',
                'major' => 'major version number of kubectl',
            ]);
        }

        if (!$errors->hasErrors()) {
            $validation = new ValidationService($config['kube'], $errors, 'host:kube');
            $validation->isArray('podSelector', 'A set of selectors to get the pod you want to connect to.');
            $validation->hasKey('namespace', 'The namespace the pod is located in.');
        }
    }

    public function createShellProcess(array $command = [], ?ShellOptions $options = null): Process
    {
        // Apply kubectl environment vars.
        $this->setShellEnvironmentVars($this->hostConfig['kube']['environment']);

        return parent::createShellProcess($command, $options);
    }

    public static function getKubectlCmd(Node $config, $kubectl_cmd = '#!kubectl', $exclude = []): array
    {
        $cmd = [$kubectl_cmd];
        if (!empty($config['kubectlOptions'])) {
            foreach ($config['kubectlOptions'] as $k => $v) {
                $cmd[] = $k;
                if ('' !== $v) {
                    $cmd[] = $v;
                }
            }
        }

        foreach (['kubeconfig', 'namespace', 'context'] as $key) {
            if (!empty($config['kube'][$key]) && !in_array($key, $exclude)) {
                $cmd[] = '--'.$key;
                $cmd[] = $config['kube'][$key];
            }
        }

        return $cmd;
    }

    protected function getKubeCmd(): array
    {
        return self::getKubectlCmd($this->getHostConfig()->getData(), 'kubectl');
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        if (empty($this->hostConfig['kube']['podForCli'])) {
            throw new \RuntimeException('Could not get shell, as podForCli is empty!');
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
     * @param string $file
     *
     * @throws \Exception
     */
    public function exists($file): bool
    {
        return $this->run(sprintf('stat %s > /dev/null 2>&1', $file), RunOptions::NONE, false)
            ->succeeded();
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

    public function wrapCommandInLoginShell(array $command): array
    {
        array_unshift(
            $command,
            '/bin/bash',
            '--login',
            '-c'
        );

        return $command;
    }

    private function kubectlCpSupportsRetries(): bool
    {
        if ($this->hostConfig->getProperty('kubectlVersion.major', 1) >= 1
            && ($this->hostConfig->getProperty('kubectlVersion.minor', 0) >= 23)) {
            return true;
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getPutFileCommand(string $source, string $dest): array
    {
        if ($this->hostConfig->getProperty('kube.useRsync')) {
            return $this->getRsyncFileCommand($source, 'rsync:'.$dest);
        }
        $command = $this->getKubeCmd();
        $command[] = 'cp';
        if ($this->kubectlCpSupportsRetries()) {
            $command[] = '--retries=999';
        }
        $command[] = trim($source);
        $command[] = $this->hostConfig['kube']['podForCli'].':'.trim($dest);

        return $command;
    }

    /**
     * @return string[]
     */
    public function getGetFileCommand(string $source, string $dest): array
    {
        if ($this->hostConfig->getProperty('kube.useRsync')) {
            return $this->getRsyncFileCommand('rsync:'.$source, $dest);
        }
        $command = $this->getKubeCmd();
        $command[] = 'cp';
        if ($this->kubectlCpSupportsRetries()) {
            $command[] = '--retries=999';
        }
        $command[] = $this->hostConfig['kube']['podForCli'].':'.trim($source);
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

    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context,
    ): int {
        $command = $this->getKubeCmd();
        $command[] = 'port-forward';
        $command[] = sprintf('pod/%s', $config['kube']['podForCli']);
        $command[] = sprintf('%d:%d', $public_port, $port);

        return $this->runProcess($command, $context, true, true);
    }

    private function getKubectlClientVersion(Node $config, string $kubectl_command): array
    {
        $fallback_version = ['major' => 1, 'minor' => 0];
        $command = self::getKubectlCmd($config, $kubectl_command);
        $command[] = 'version';
        $command[] = '--output=json';

        $process = new Process($command);
        $process->setTimeout(60 * 60);
        $process->run();
        if (0 !== $process->getExitCode()) {
            $this->logger->log($this->errorLogLevel->get(), $process->getErrorOutput());

            return $fallback_version;
        }

        $output = $process->getOutput();

        $client_version = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return $client_version['clientVersion'] ?? $fallback_version;
    }
}
