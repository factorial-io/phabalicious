<?php

namespace Phabalicious\Command;

class DatabaseInstallCommand extends DatabaseSubCommand
{
    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'install',
            'description' => 'Install a new database',
            'help' => '
Installs a new database.

This command will create a new database if there is no existing one.

Examples:
<info>phab --config=myconfig db:install</info>
            ',
        ];
    }
}
