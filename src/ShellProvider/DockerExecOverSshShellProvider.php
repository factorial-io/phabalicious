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

    public function getShellCommand(array $program_to_call, array $options = []): array
    {
        $command = $this->dockerExec->getShellCommand([], $options);

        $ssh_command = parent::getShellCommand([], $options);

        if (count($program_to_call)) {
            $command[] = implode(' ', $program_to_call);
        }
        $ssh_command[] = implode(' ', $command);

        return $ssh_command;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($dir):bool
    {
        return $this->dockerExec->exists($dir);
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
        
        return $this->dockerExec->putFile($tmp_dest, $dest, $context, $verbose);
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $tmp_source = tempnam($this->hostConfig['tmpFolder'], $dest);
        if (!$this->dockerExec->getFile($source, $tmp_source, $context, $verbose)) {
            return false;
        }
        return parent::putFile($tmp_source, $dest, $context, $verbose);
    }


    /**
     * {@inheritdoc}
     */
    public function wrapCommandInLoginShell(array $command)
    {
        return [
            '/bin/sh',
            '--login',
            '-c',
            '\'' . implode(" ", $command) . '\'',
        ];
    }
}
