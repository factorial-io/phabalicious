<?php

namespace Phabalicious\Utilities;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParallelExecutor
{
    /** @var ParallelExecutorRun[] */
    private array $runs = [];
    private int $maxSimultaneous;

    public function __construct(array $command_lines, OutputInterface $output, int $max_simultaneous_processes = 4)
    {
        $this->maxSimultaneous = $max_simultaneous_processes;

        foreach ($command_lines as $identifier => $cmd) {
            $this->add(new ParallelExecutorRun(
                $identifier,
                $cmd,
                $output instanceof ConsoleOutput
                    ? $output->section()
                    : null
            ));
        }
    }

    public function execute(InputInterface $input, OutputInterface $output, ?string $save_as_json): bool
    {
        $progress_section = $output instanceof ConsoleOutput
            ? $output->section()
            : $output;
        $total = count($this->runs);
        $progress = new ProgressBar($progress_section, $total);

        $output->writeln('');
        $progress->display();

        $completed = 0;
        $interval = 200000;
        $allSuccessful = true;

        while ($completed < $total) {
            // Start new processes up to the max simultaneous limit.
            $running = 0;
            foreach ($this->runs as $run) {
                if ($run->isStarted() && $run->isRunning()) {
                    $running++;
                }
            }
            foreach ($this->runs as $run) {
                if (!$run->isStarted() && $running < $this->maxSimultaneous) {
                    $run->start();
                    $running++;
                }
            }

            // Check for completed processes.
            foreach ($this->runs as $run) {
                if ($run->isStarted() && $run->isTerminated()) {
                    // Only count newly terminated ones.
                    if (!isset($this->finishedSet[spl_object_id($run)])) {
                        $this->finishedSet[spl_object_id($run)] = true;
                        $run->notifyFinished();
                        if (!$run->isSuccessful()) {
                            $allSuccessful = false;
                        }
                        $completed++;
                        $progress->setProgress($completed);
                    }
                }
            }

            if ($completed < $total) {
                usleep($interval);
            }
        }

        $progress->finish();
        $style = new SymfonyStyle($input, $output);

        $data = [];
        foreach ($this->runs as $run) {
            $style->section(sprintf('Results of `%s`', $run->getCommandLine()));
            $data[$run->getIdentifier()] = [
                'command' => $run->getCommandLine(),
                'exit_code' => $run->getProcess()->getExitCode(),
                'output' => $run->getProcess()->getOutput(),
                'error_output' => $run->getProcess()->getErrorOutput(),
            ];
            $style->writeln($run->getProcess()->getOutput());
            $error = $run->getProcess()->getErrorOutput();
            if (!empty($error)) {
                $style->comment('Error output:');
                $style->writeln($run->getProcess()->getErrorOutput());
            }
        }

        if ($save_as_json) {
            file_put_contents($save_as_json, json_encode($data, JSON_PRETTY_PRINT));
        }

        return $allSuccessful;
    }

    public function add(ParallelExecutorRun $run): void
    {
        $this->runs[] = $run;
    }

    /** @var array<int, bool> */
    private array $finishedSet = [];
}
