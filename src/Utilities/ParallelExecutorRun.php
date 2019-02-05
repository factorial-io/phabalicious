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

    public function __construct($command_line, ConsoleSectionOutput $output = null)
    {
        $this->output = $output;
        $this->commandLine = implode(' ', $command_line);

        parent::__construct(new Process($command_line));
        if ($output) {
            $this->addListeners();
        }
    }

    public function addListeners()
    {
        $this->writeln("<fg=blue>Waiting</>");

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
                $run = $event->getRun();
                $exceptions = $run->getExceptions();
                $exception = null;
                if (count($exceptions) > 0) {
                    $exception = reset($exceptions);
                    $error = sprintf(
                        "<error>x Failed</error> (%d) %s",
                        $exception->getCode(),
                        $exception->getMessage()
                    );
                } else {
                    $error = "<error>x Failed</error>";
                }
                $this->writeln($error);
                if ($exception) {
                    $this->writeln($exception->getMessage());
                }
            }
        );
    }

    public function writeln($message)
    {
        $this->output->overwrite($this->commandLine . ': ' . $message);
    }

    public function getCommandLine()
    {
        return $this->commandLine;
    }
}
