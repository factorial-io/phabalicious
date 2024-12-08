<?php

namespace Phabalicious\Command;

use Phabalicious\ShellProvider\ShellOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseShellCommand extends DatabaseSubCommand
{
    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'shell',
            'description' => 'Get a sql shell',
            'help' => 'Get a shell to the database to execute commands directly.',
        ];
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = BaseCommand::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $context->set('what', 'shell');

        $this->getMethods()->runTask('database', $this->getHostConfig(), $context);

        // Allow methods to override the used shellProvider:
        $shell_command = $context->getResult('shell-command');
        if (!$shell_command) {
            throw new \RuntimeException('Could not get shell-command from database method!');
        }

        $output->writeln('<info>Starting database shell on `'.$this->getHostConfig()->getConfigName().'`');

        /** @var \Phabalicious\ShellProvider\ShellProviderInterface $shell */
        $shell = $context->getResult('shell', $this->getHostConfig()->shell());

        $options = new ShellOptions();
        $options->setUseTty(true);
        $options->setQuiet(false);

        $process = $this->startInteractiveShell($context, $shell, [implode(' ', $shell_command)], $options);

        return $process->getExitCode();
    }
}
