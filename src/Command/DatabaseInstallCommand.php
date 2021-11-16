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
