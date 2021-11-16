<?php

namespace Phabalicious\Command;

use Phabalicious\ShellProvider\ShellOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseShellCommandCommand extends DatabaseSubCommand
{

    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'shell:command',
            'description' => 'Print out the command to get a sql shell',
            'help' => 'Print out the command to get a sql shell',
        ];
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

        if ($result = BaseCommand::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $context->set('what', 'shell');

        $this->getMethods()->runTask('databaseShellPrepare', $this->getHostConfig(), $context);
        $this->getMethods()->runTask('database', $this->getHostConfig(), $context);

        // Allow methods to override the used shellProvider:
        $shell_command = $context->getResult('shell-command');
        if (!$shell_command) {
            throw new \RuntimeException('Could not get shell-command from database method!');
        }

        /** @var \Phabalicious\ShellProvider\ShellProviderInterface $shell */
        $shell = $context->getResult('shell', $this->getHostConfig()->shell());

        $options = new ShellOptions();
        $options->setUseTty(true);
        $options->setQuiet(false);
        $options->setShellExecutableProvided(true);

        $shell_command = $shell->getShellCommand([ implode(' ', $shell_command) ], $options);
        $output->writeln(implode(' ', $shell_command));


        return 0;
    }
}
