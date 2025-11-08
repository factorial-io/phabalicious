<?php

namespace Phabalicious\Command;

use InvalidArgumentException;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\DatabaseMethod;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RestoreSqlFromFileCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('restore:sql-from-file')
            ->setDescription('Restores a database from a sql-file')
            ->setHelp('
Restores a database from a local SQL dump file.

This command uploads a local SQL dump file to the remote host and imports it
into the database. It is useful for restoring database backups or migrating
data from one environment to another.

Behavior:
- Verifies the specified file exists locally
- Copies the SQL dump file to the remote host\'s tmpFolder
- Drops the existing database by default (unless --skip-drop-db is set)
- Imports the SQL dump into the database
- Removes the temporary file from the remote host after import
- Shows success message if import completes successfully

The file is uploaded with a timestamp in its name to avoid conflicts.

Arguments:
- <file>: Path to the local SQL dump file to restore

Options:
- --skip-drop-db: Do not drop the database before importing (merge data instead)

Examples:
<info>phab --config=myconfig restore:sql-from-file backup.sql</info>
<info>phab --config=myconfig restore:sql-from-file /path/to/dump.sql</info>
<info>phab --config=myconfig restore:sql-from-file dump.sql --skip-drop-db</info>
<info>phab --config=myconfig restoreSQLFromFile backup.sql</info>  # Using alias
            ');
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The file containing the sql-dump'
        );
        $this->addOption(
            'skip-drop-db',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip dropping the db before running the import',
            false
        );

        $this->setAliases(['restoreSQLFromFile']);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
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
        $file = $input->getArgument('file');
        if (!file_exists($file)) {
            throw new InvalidArgumentException('Could not find file at `' . $file . '`');
        }

        $host_config = $this->getHostConfig();
        $this->getMethods()->runTask('restoreSqlFromFilePreparation', $host_config, $context);

        $shell = $host_config->shell();
        $dest = $host_config['tmpFolder'] . '/' .
            $host_config->getConfigName() . '.' .
            date('YmdHis') . '.' .
            basename($file);

        $context->io()->comment(sprintf('Copying dump to `%s` ...', $dest));
        $shell->putFile($file, $dest, $context);
        $context->set('source', $dest);
        $context->set(DatabaseMethod::DROP_DATABASE, !Utilities::hasBoolOptionSet($input, 'skip-drop-db'));

        $context->io()->comment(sprintf('Restoring `%s` from `%s` ...', $host_config->getConfigName(), $dest));
        $this->getMethods()->runTask('restoreSqlFromFile', $host_config, $context);

        $shell->run(sprintf('rm %s', $dest));
        $exitCode = $context->getResult('exitCode', 0);
        if ($exitCode == 0) {
            $context->io()->success('SQL dump imported successfully!');
        }

        return $exitCode;
    }
}
