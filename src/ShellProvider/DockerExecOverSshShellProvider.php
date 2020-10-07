<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\DockerMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DockerExecOverSshShellProvider extends SshShellProvider implements ShellProviderInterface
{
    const PROVIDER_NAME = 'docker-exec-over-ssh';

    /**
     * @var DockerExecShellProvider
     */
    protected $dockerExec;

    /**
     * Shell to run docker commands on host.
     *
     * @var ShellProviderInterface
     */
    protected $sshShell;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->dockerExec = new DockerExecShellProvider($logger);
    }

    public function setHostConfig(HostConfig $config)
    {
        parent::setHostConfig($config);
        $this->dockerExec->setHostConfig($config);
    }

    public function setOutput(OutputInterface $output)
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

    public function pushWorkingDir(string $new_working_dir)
    {
        parent::pushWorkingDir($new_working_dir);
        $this->dockerExec->pushWorkingDir($new_working_dir);
    }

    public function popWorkingDir()
    {
        parent::popWorkingDir();
        $this->dockerExec->popWorkingDir();
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result =  parent::getDefaultConfig($configuration_service, $host_config);
        $result['dockerExecutable'] = 'docker';

        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
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
        $ssh_command = array_merge($ssh_command, $command);
        // $ssh_command[] = implode(' ', $command);

        return $ssh_command;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($dir):bool
    {
        $result = $this->run(sprintf('stat %s > /dev/null 2>&1', $dir), false, false);
        return $result->succeeded();
    }

    private function ensureSshShell()
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

    /**
     * {@inheritdoc}
     */
    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $tmp_dest = tempnam($this->hostConfig['tmpFolder'], $dest);

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
        $tmp_source = tempnam($this->hostConfig['tmpFolder'], $dest);

        $this->ensureSshShell();
        $cmd = $this->dockerExec->getGetFileCommand($source, $tmp_source);
        $result = $this->sshShell->run(implode(' ', $cmd));
        if ($result->failed()) {
            return false;
        }

        $result = parent::putFile($tmp_source, $dest, $context, $verbose);
        $this->sshShell->run(sprintf('rm %s', escapeshellarg($tmp_source)));

        return $result;
    }


    /**
     * {@inheritdoc}
     */
    public function wrapCommandInLoginShell(array $command)
    {
        return [
            '/bin/sh',
            '-l',
            '-c',
            '\'' . implode(" ", $command) . '\'',
        ];
    }
}
