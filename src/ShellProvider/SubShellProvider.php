<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class SubShellProvider extends BaseShellProvider
{
    protected ShellProviderInterface $parentShell;

    public function __construct(LoggerInterface $logger, ShellProviderInterface $parent_shell)
    {
        parent::__construct($logger);
        $this->parentShell = $parent_shell;

        $this->setFileOperationsHandler(new UnsupportedFileOperations());
    }

    public function getName(): string
    {
        return 'sub-shell';
    }

    public function exists($file): bool
    {
        return $this->run(sprintf('stat %s > /dev/null 2>&1', $file), RunOptions::NONE, false)
            ->succeeded();
    }

    public function run(string $command, RunOptions $run_options = RunOptions::NONE, $throw_exception_on_error = false): CommandResult
    {
        $this->parentShell->cd($this->getWorkingDir());

        return $this->parentShell->run($command, $run_options, $throw_exception_on_error);
    }

    public function cd(string $dir): ShellProviderInterface
    {
        parent::cd($dir);
        $this->parentShell->cd($dir);

        return $this;
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \LogicException('getFile not implemented');
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \LogicException('putFile not implemented');
    }

    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context,
    ): int {
        throw new \LogicException('startRemoteAccess not implemented');
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        throw new \LogicException('getShellCommand not implemented');
    }

    public function createShellProcess(array $command = [], ?ShellOptions $options = null): Process
    {
        throw new \LogicException('createShellProcess not implemented');
    }

    public function createTunnelProcess(HostConfig $target_config, array $prefix = []): Process
    {
        throw new \LogicException('createTunnelProcess not implemented');
    }

    public function wrapCommandInLoginShell(array $command): array
    {
        throw new \LogicException('wrapCommandInLoginShell not implemented');
    }

    public function terminate(): void
    {
        $this->parentShell->terminate();
    }

    public function startSubShell(array $cmd): ShellProviderInterface
    {
        throw new \LogicException('Could not start subshelll in subshell');
    }
}
