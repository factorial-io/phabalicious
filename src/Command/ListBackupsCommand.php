<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListBackupsCommand extends BackupBaseCommand
{
    protected function configure()
    {
        $this
            ->setName('list:backups')
            ->setDescription('List all backups')
            ->setHelp('Displays a list of all backups for a givebn configuration');
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Filter list of backups by type, if none given, all types get displayed',
            []
        );

        $this->setAliases(['listBackups']);
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

        $this->getMethods()->runTask('listBackups', $this->getHostConfig(), $context);

        $files = $context->getResult('files');
        $files = array_filter($files, function ($file) use ($what) {
            return in_array($file['type'], $what);
        });
        uasort($files, function ($a, $b) {
            if ($a['date'] == $b['date']) {
                if ($a['time'] == $b['time']) {
                    return strcmp($a['type'], $b['type']);
                }

                return strcmp($b['time'], $a['time']);
            }

            return strcmp($b['date'], $a['date']);
        });
        $io = new SymfonyStyle($input, $output);
        $io->title('List of backups');
        $io->table(
            ['Date', 'Time', 'Type', 'Hash', 'File'],
            array_map(function ($file) {
                return [
                    $file['date'],
                    $file['time'],
                    $file['type'],
                    $file['hash'],
                    $file['file'],
                ];
            }, $files)
        );

        return $context->getResult('exitCode', 0);
    }
}
