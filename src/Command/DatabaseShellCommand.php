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
            'help' => '
Opens an interactive SQL shell to the database.

This command will open an interactive database shell (e.g., mysql, psql) allowing you to
execute SQL commands directly against the database. The shell runs in TTY mode for
interactive use.

The specific shell command (mysql, psql, etc.) is determined by the database method
configured for your host.

Examples:
<info>phab --config=myconfig db:shell</info>
            ',
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

        $output->writeln('<info>Starting database shell on `' . $this->getHostConfig()->getConfigName() . '`');

        /** @var \Phabalicious\ShellProvider\ShellProviderInterface $shell */
        $shell = $context->getResult('shell', $this->getHostConfig()->shell());

        $options = new ShellOptions();
        $options->setUseTty(true);
        $options->setQuiet(false);

        $process = $this->startInteractiveShell($context, $shell, [ implode(' ', $shell_command) ], $options);
        return $process->getExitCode();
    }
}
