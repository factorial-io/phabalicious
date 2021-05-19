<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\SetAndRestoreObjProperty;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class DryRunShellProvider extends BaseShellProvider implements ShellProviderInterface
{

    const PROVIDER_NAME = 'dry-run';

    protected $captured = [];

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [];
    }


    /**
     * Run a command in the shell.
     *
     * @param string $command
     * @param bool $capture_output
     * @param bool $throw_exception_on_error
     * @return CommandResult
     * @throws FailedShellCommandException
     * @throws \RuntimeException
     */
    public function run(string $command, $capture_output = false, $throw_exception_on_error = true): CommandResult
    {
        $command = sprintf("cd %s && %s", $this->getWorkingDir(), $this->expandCommand($command));
        if (substr($command, -1) == ';') {
            $command = substr($command, 0, -1);
        }
        $this->captured[] = $command;
        if ($this->output) {
            $saved = $this->output->getVerbosity();
            $this->output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            $this->output->writeln($command);
            $this->output->setVerbosity($saved);
        }

        $cr = new CommandResult(0, []);
        if ($cr->failed() && !$capture_output && $throw_exception_on_error) {
            $cr->throwException(sprintf('`%s` failed!', $command));
        }
        return $cr;
    }

    public function exists($file): bool
    {
        return true;
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException("getFile not implemented!");
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException("putFile not implemented!");
    }

    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context
    ) {
        throw new \RuntimeException("startRemoteAccess not implemented!");
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        throw new \RuntimeException("getShellCommand not implemented!");
    }

    public function createShellProcess(array $command = [], ShellOptions $options = null): Process
    {
        throw new \RuntimeException("createShellProcess not implemented!");
    }

    public function createTunnelProcess(HostConfig $target_config, array $prefix = [])
    {
        throw new \RuntimeException("createTunnelProcess not implemented!");
    }

    public function wrapCommandInLoginShell(array $command)
    {
        return $command;
    }

    /**
     * @return array
     */
    public function getCapturedCommands(): array
    {
        return $this->captured;
    }

    public function terminate()
    {
        // Nothing to see here.
    }
}
