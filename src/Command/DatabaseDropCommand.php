<?php

namespace Phabalicious\Command;

class DatabaseDropCommand extends DatabaseSubCommand
{
    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'drop',
            'description' => 'Drop all tables in the database',
            'help' => 'Drop all tables in the database',
        ];
    }
}
