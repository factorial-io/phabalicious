<?php

namespace Phabalicious\Command;

class DatabaseInstallCommand extends DatabaseSubCommand
{
    public function getSubcommandInfo(): array
    {
        return [
            'subcommand' => 'install',
            'description' => 'Install a new database',
            'help' => 'Install a new database if there is no existing one',
        ];
    }
}
