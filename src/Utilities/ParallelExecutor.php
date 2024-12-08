<?php

namespace Phabalicious\Utilities;

use Graze\ParallelProcess\PriorityPool;
use Graze\ParallelProcess\ProcessRun;
use Graze\ParallelProcess\RunInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParallelExecutor
{
    private $pool;

    public function __construct($command_lines, OutputInterface $output, $max_simultaneous_processes = 4)
    {
        $this->pool = new PriorityPool();
        $this->pool->setMaxSimultaneous($max_simultaneous_processes);

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

    public function execute(InputInterface $input, OutputInterface $output, ?string $save_as_json)
    {
        $progress_section = $output instanceof ConsoleOutput
            ? $output->section()
            : $output;
        $progress = new ProgressBar($progress_section, $this->pool->count());

        $this->pool->start();
        $output->writeln('');
        $progress->display();

        $interval = 200000;
        $current = 0;
        $previous = 0;
        while ($this->pool->poll()) {
            usleep($interval);
            $p = $this->pool->getProgress();
            $current = $p[0];
            $progress->advance($current - $previous);
            $previous = $current;
        }
        $progress->finish();
        $style = new SymfonyStyle($input, $output);

        $data = [];
        foreach ($this->pool->getAll() as $run) {
            if ($run instanceof ParallelExecutorRun) {
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
        }

        if ($save_as_json) {
            file_put_contents($save_as_json, json_encode($data, JSON_PRETTY_PRINT));
        }

        return $this->pool->isSuccessful();
    }

    public function add(ParallelExecutorRun $run)
    {
        $this->pool->add($run);
    }

    private function format(RunInterface $run, string $message)
    {
        if ($run instanceof ProcessRun) {
            $cmd = $run->getProcess()->getCommandLine();

            return implode(' ', $cmd).': '.$message;
        }

        return $message;
    }
}
