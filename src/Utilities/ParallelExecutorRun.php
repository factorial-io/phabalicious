<?php

namespace Phabalicious\Utilities;

use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Process\Process;

class ParallelExecutorRun
{
    private ?ConsoleSectionOutput $output;
    private string $commandLine;
    protected string $identifier;
    private Process $process;
    private bool $started = false;

    public function __construct(string $identifier, array $command_line, ?ConsoleSectionOutput $output = null)
    {
        $this->identifier = $identifier;
        $this->output = $output;
        $this->commandLine = implode(' ', $command_line);
        $this->process = new Process($command_line);

        if ($output) {
            $this->writeln('<fg=blue>~ waiting</>');
        }
    }

    public function start(): void
    {
        $this->started = true;
        $this->process->start();
        if ($this->output) {
            $this->writeln('<fg=blue>→ Started</>');
        }
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function isTerminated(): bool
    {
        return $this->process->isTerminated();
    }

    public function isSuccessful(): bool
    {
        return $this->process->isSuccessful();
    }

    public function notifyFinished(): void
    {
        if ($this->output) {
            if ($this->process->isSuccessful()) {
                $this->writeln('<info>✓ Succeeded</info>');
            } else {
                $this->writeln('<error>x Failed</error>');
            }
        }
    }

    public function writeln(string $message): void
    {
        if ($this->output) {
            $this->output->overwrite($this->commandLine . ': ' . $message);
        }
    }

    public function getCommandLine(): string
    {
        return $this->commandLine;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }
}
