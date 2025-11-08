<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupCommand extends BackupBaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('backup')
            ->setDescription('Backups all data of an application')
            ->setHelp('
Backup your files and database into the specified backup-directory.

The file-names will include configuration-name, a timestamp and the git-SHA1 (if available).
Every backup can be referenced by its filename (w/o extension) or, when git is available,
via the git-commit-hash.

If <what> is omitted, files and db gets backed up. You can limit this by providing db and/or files.

Your host-configuration will need a backupFolder and a filesFolder.

Examples:
<info>phab --config=myconfig backup</info>
<info>phab --config=myconfig backup files</info>
<info>phab --config=myconfig backup db</info>
<info>phab --config=myconfig backup db files</info>
            ');
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to backup, allowed are `db` and `files`, if nothing is set, everything will be backed up',
            []
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int

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
        $context->setResult('basename', [
           $this->getHostConfig()->getConfigName(),
           date('Y-m-d--H-i-s')
        ]);

        try {
            $this->getMethods()->runTask('backup', $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        $files = $context->getResult('files', []);

        if (count($files)) {
            $io = new SymfonyStyle($input, $output);
            $io->title('Created backup files');
            $io->table(
                ['Type', 'File'],
                $files
            );

            $context->io()->success('Backups created successfully!');
        }

        return $context->getResult('exitCode', 0);
    }
}
