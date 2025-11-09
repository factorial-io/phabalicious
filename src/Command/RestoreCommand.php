<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestoreCommand extends BackupBaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('restore')
            ->setDescription('Restores a given backup-set')
            ->setHelp('
Restores a backup set created with the backup command.

This command restores a previously created backup by its hash or git commit SHA.
Backups are created with the "backup" command and stored in the configured backupFolder.

Behavior:
- Looks up the backup set by the provided hash (can be a short hash or full hash)
- If using git, you can reference backups by git commit SHA
- Restores database and/or files from the backup
- Displays a table showing which files were restored
- If <what> is omitted, both database and files are restored

The hash can be:
- The backup filename (without extension)
- A git commit hash (if backups are git-tagged)
- A short version of either

Arguments:
- <hash>: Hash or identifier of the backup set to restore
- <what>: Space-separated list of what to restore (optional, defaults to "db files")
         Valid values: db, files

Examples:
<info>phab --config=myconfig restore abc123</info>
<info>phab --config=myconfig restore abc123 db</info>        # Only restore database
<info>phab --config=myconfig restore abc123 files</info>     # Only restore files
<info>phab --config=myconfig restore abc123 db files</info>  # Restore both
            ');
        $this->addArgument(
            'hash',
            InputArgument::REQUIRED,
            'The hash of the backup-set to restore'
        );
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to restore, if ommitted files and db will be restored',
            []
        );
    }

    /**
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $what = $this->collectBackupMethods($input, $context);
        $context->set('what', $what);

        $hash = $input->getArgument('hash');

        $to_restore = $this->findBackupSet($hash, $context);

        $context->set('backup_set', $to_restore);
        $context->setResult('files', null);

        try {
            $this->getMethods()->runTask('restore', $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        $files = $context->getResult('files', []);

        if (count($files) > 0) {
            $io = new SymfonyStyle($input, $output);
            $io->title('Restored backup-set:');
            $io->table(
                ['Type', 'File'],
                $files
            );
        }

        return $context->getResult('exitCode', 0);
    }
}
