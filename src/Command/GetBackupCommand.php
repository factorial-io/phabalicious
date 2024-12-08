<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetBackupCommand extends BackupBaseCommand
{
    protected function configure()
    {
        $this
            ->setName('get:backup')
            ->setDescription('Get a specific backup-set')
            ->setHelp('Copies a backup-set to the local computer');
        $this->addArgument(
            'hash',
            InputArgument::REQUIRED,
            'The hash of the backup-set to copy'
        );
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to copy, if ommitted files and db will be copied',
            ['files', 'db']
        );
        $this->setAliases(['getBackup']);
        parent::configure();
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
        $to_copy = $this->findBackupSet($hash, $context);

        $shell = $this->getHostConfig()->shell();
        $files = [];
        foreach ($to_copy as $elem) {
            if (!in_array($elem['type'], $what)) {
                continue;
            }
            if ($shell->getFile(
                $this->getHostConfig()['backupFolder'].'/'.$elem['file'],
                getcwd().'/'.$elem['file'],
                $context
            )) {
                $files[] = [
                    'type' => $elem['type'],
                    'file' => $elem['file'],
                ];
            }
        }

        if (count($files) > 0) {
            $io = $context->io();
            $io->title('Copied backup-set:');
            $io->table(
                ['Type', 'File'],
                $files
            );
        }

        return 0;
    }
}
