<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\DatabaseMethod;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseQueryCommand extends DatabaseSubCommand
{

    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'query',
            'description' => 'Run a query against the database',
            'help' => '
Runs a query against the database and displays the results.

This command will execute a SQL query on the database and output the results.

Examples:
<info>phab --config=myconfig db:query "SELECT * FROM users LIMIT 10"</info>
<info>phab --config=myconfig db:query "SHOW TABLES"</info>
            ',
        ];
    }

    public function getSubcommandArguments(): array
    {
        return [DatabaseMethod::SQL_QUERY => 'query to execute'];
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->createContext($input, $output);
        $this->getContext()->io()->comment('Querying database ...');

        $result = parent::execute($input, $output);

        if (!$result) {
            $lines = $this->getContext()->getCommandResult()->getOutput();
            $this->getContext()->io()->title("Result");
            $output->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
            foreach ($lines as $line) {
                $output->writeln($line);
            }
        }
        return $this->getContext()->getResult('exitCode', 0);
    }
}
