<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\DockerMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerExecOverSshShellProvider extends SshShellProvider
{
    public const PROVIDER_NAME = 'docker-exec-over-ssh';

    /**
     * @var DockerExecShellProvider
     */
    protected DockerExecShellProvider $dockerExec;

    /**
     * Shell to run docker commands on host.
     *
     * @var ShellProviderInterface
     */
    protected ShellProviderInterface $sshShell;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->dockerExec = new DockerExecShellProvider($logger);
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function setHostConfig(HostConfig $config): void
    {
        parent::setHostConfig($config);
        $this->dockerExec->setHostConfig($config);
    }

    public function setOutput(OutputInterface $output): void
    {
        parent::setOutput($output);
        $this->dockerExec->setOutput($output);
    }

    public function cd(string $dir): ShellProviderInterface
    {
        parent::cd($dir);
        $this->dockerExec->cd($dir);

        return $this;
    }

    public function pushWorkingDir(string $new_working_dir): void
    {
        parent::pushWorkingDir($new_working_dir);
        $this->dockerExec->pushWorkingDir($new_working_dir);
    }

    public function popWorkingDir(): void
    {
        parent::popWorkingDir();
        $this->dockerExec->popWorkingDir();
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent =  parent::getDefaultConfig($configuration_service, $host_config);
        $result = [];
        $result['dockerExecutable'] = 'docker';

        return $parent->merge(new Node($result, $this->getName() . ' shellprovider defaults'));
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors): void
    {
        parent::validateConfig($config, $errors);
        $this->dockerExec->validateConfig($config, $errors);
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        $command = $this->dockerExec->getShellCommand([], $options);

        $ssh_command = parent::getShellCommand([], $options);

        if (count($program_to_call)) {
            $command[] = implode(' ', $program_to_call);
        }

        return array_merge($ssh_command, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($file):bool
    {
        return $this->run(sprintf('stat %s > /dev/null 2>&1', $file), false, false)
            ->succeeded();
    }

    private function ensureSshShell(): void
    {
        if ($this->sshShell) {
            return;
        }
        $docker_config = clone $this
            ->hostConfig
            ->getConfigurationService()
            ->getDockerConfig($this->hostConfig['docker']['configuration']);

        $this->sshShell = new SshShellProvider($this->logger);
        $this->sshShell->setHostConfig($docker_config);
        $this->sshShell->setOutput($this->output);
        $this->sshShell->cd(DockerMethod::getProjectFolder($docker_config, $this->hostConfig));
    }

    protected function getTempFileName($str): string
    {
        return Utilities::getTempFileName($this->getHostConfig(), $str);
    }

    /**
     * {@inheritdoc}
     */
    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $tmp_dest = $this->getTempFileName($dest);

        if (!parent::putFile($source, $tmp_dest, $context, $verbose)) {
            return false;
        }

        $this->ensureSshShell();
        $cmd = $this->dockerExec->getPutFileCommand($tmp_dest, $dest);
        $result = $this->sshShell->run(implode(' ', $cmd));

        $this->sshShell->run(sprintf('rm %s', escapeshellarg($tmp_dest)));

        return $result->succeeded();
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $tmp_source = $this->getTempFileName($source);

        $this->ensureSshShell();
        $cmd = $this->dockerExec->getGetFileCommand($source, $tmp_source);
        $result = $this->sshShell->run(implode(' ', $cmd));
        if ($result->failed()) {
            return false;
        }

        $result = parent::getFile($tmp_source, $dest, $context, $verbose);
        $this->sshShell->run(sprintf('rm %s', escapeshellarg($tmp_source)));

        return $result;
    }


    /**
     * {@inheritdoc}
     */
    public function wrapCommandInLoginShell(array $command): array
    {
        return [
            '/bin/sh',
            '-l',
            '-c',
            '\'' . implode(" ", $command) . '\'',
        ];
    }
}
