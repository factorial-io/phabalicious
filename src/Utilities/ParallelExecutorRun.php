<?php

namespace Phabalicious\Utilities;

use Graze\ParallelProcess\Event\RunEvent;
use Graze\ParallelProcess\ProcessRun;

use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ParallelExecutorRun extends ProcessRun
{


    /** @var ConsoleSectionOutput */
    private $output;
    private $commandLine;

    protected $identifier;

    public function __construct(string $identifier, $command_line, ?ConsoleSectionOutput $output = null)
    {
        $this->identifier = $identifier;
        $this->output = $output;
        $this->commandLine = implode(' ', $command_line);

        parent::__construct(new Process($command_line));
        if ($output) {
            $this->addListeners();
        }
    }

    public function addListeners(): void
    {
        $this->writeln("<fg=blue>~ waiting</>");

        $this->addListener(
            RunEvent::STARTED,
            function (RunEvent $event) {
                $this->writeln("<fg=blue>→ Started</>");
            }
        );
        $this->addListener(
            RunEvent::SUCCESSFUL,
            function (RunEvent $event) {
                $this->writeln("<info>✓ Succeeded</info>");
            }
        );
        $this->addListener(
            RunEvent::FAILED,
            function (RunEvent $event) {
                $this->writeln("<error>x Failed</error>");
            }
        );
    }

    public function writeln($message): void
    {
        $this->output->overwrite($this->commandLine . ': ' . $message);
    }

    public function getCommandLine(): string
    {
        return $this->commandLine;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
