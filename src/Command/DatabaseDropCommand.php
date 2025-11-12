<?php

namespace Phabalicious\Command;

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
