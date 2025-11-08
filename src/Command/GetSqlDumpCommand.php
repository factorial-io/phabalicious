<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GetSqlDumpCommand extends BaseCommand
{

    protected function configure()
    {
        $this
            ->setName('get:sql-dump')
            ->setDescription('Get a current dump of the database')
            ->setHelp('
Creates a database dump on the remote host and copies it to your local computer.

This command creates a SQL dump of the remote database, downloads it to your
local machine, and then removes the temporary dump file from the remote server.

Behavior:
- Creates a SQL dump on the remote host
- Downloads the dump file to the current working directory
- Removes the temporary dump file from the remote host
- Optionally renames the file if --output is specified
- Displays a list of downloaded files

The dump file naming and compression depend on the database method configuration.

Options:
- --output, -o: Rename the downloaded file to this name

Examples:
<info>phab --config=myconfig get:sql-dump</info>
<info>phab --config=production get:sql-dump --output=prod-backup.sql.gz</info>
<info>phab --config=myconfig getSQLDump</info>  # Using alias
            ');
        $this->setAliases(['getSQLDump']);
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_OPTIONAL,
            'The file to copy the dump to',
            ''
        );
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();

        $this->getMethods()->runTask('getSQLDump', $this->getHostConfig(), $context);
        $to_copy = $context->getResult('files', []);

        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $this->getHostConfig()->shell());
        $files = [];
        foreach ($to_copy as $file) {
            if ($shell->getFile(
                $file,
                getcwd() . '/' . basename($file),
                $context
            )) {
                $files[] = basename($file);
            }
            $shell->run(sprintf('rm %s', $file));
        }
        if ((count($files) > 0) && $input->getOption('output')) {
            $local_shell = new LocalShellProvider($this->configuration->getLogger());
            $local_shell->setHostConfig(new HostConfig([
                'rootFolder' => getcwd(),
                'shellExecutable' => '/bin/bash'
            ], $local_shell, $this->configuration));

            $file = reset($files);
            $target_file = $input->getOption('output');
            $local_shell->run(sprintf('rm -f %s', $target_file));
            $local_shell->run(sprintf('mv %s %s', $file, $target_file));

            $files = [$target_file];
        }

        if (count($files) > 0) {
            $io = new SymfonyStyle($input, $output);
            $io->title('Copied dumps to:');
            $io->listing($files);
        }


        return 0;
    }
}
