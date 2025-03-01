<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContextInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DryRunShellProvider extends BaseShellProvider
{
    public const PROVIDER_NAME = 'dry-run';

    protected array $captured = [];

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->setFileOperationsHandler(new NoopFileOperations());
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        return new Node([], $this->getName().' shellprovider defaults');
    }

    /**
     * Run a command in the shell.
     *
     * @param bool $throw_exception_on_error
     *
     * @throws \Phabalicious\Exception\FailedShellCommandException
     */
    public function run(string $command, RunOptions $run_options = RunOptions::NONE, $throw_exception_on_error = true): CommandResult
    {
        $command = sprintf('cd %s && %s', $this->getWorkingDir(), $this->expandCommand($command));
        if (str_ends_with($command, ';')) {
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
        if ($throw_exception_on_error && $cr->failed() && !$run_options->isCapturingOutput()) {
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
        throw new \RuntimeException('getFile not implemented!');
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException('putFile not implemented!');
    }

    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context,
    ): bool {
        throw new \RuntimeException('startRemoteAccess not implemented!');
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        throw new \RuntimeException('getShellCommand not implemented!');
    }

    public function createShellProcess(array $command = [], ?ShellOptions $options = null): Process
    {
        throw new \RuntimeException('createShellProcess not implemented!');
    }

    public function createTunnelProcess(HostConfig $target_config, array $prefix = []): Process
    {
        throw new \RuntimeException('createTunnelProcess not implemented!');
    }

    public function wrapCommandInLoginShell(array $command): array
    {
        return $command;
    }

    public function getCapturedCommands(): array
    {
        return $this->captured;
    }

    public function terminate(): void
    {
        // Nothing to see here.
    }

    public function startSubShell(array $cmd): ShellProviderInterface
    {
        return $this;
    }
}
