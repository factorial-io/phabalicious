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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseDropCommand extends DatabaseSubCommand
{

    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'drop',
            'description' => 'Drop all tables in the database',
            'help' => '
Drops all tables in the database.

This command will remove all tables from the database. Use with caution!

Examples:
<info>phab --config=myconfig db:drop</info>
            ',
        ];
    }
}
