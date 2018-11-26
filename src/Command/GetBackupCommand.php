<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GetBackupCommand extends BaseCommand
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
     * @param InputInterface $input
     * @param OutputInterface $output
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $what = array_map(function ($elem) {
            return trim(strtolower($elem));
        }, $input->getArgument('what'));

        $context = new TaskContext($this, $input, $output);
        $context->set('what', $what);

        $hash = $input->getArgument('hash');

        $this->getMethods()->runTask('listBackups', $this->getHostConfig(), $context);
        $backup_sets = $context->getResult('files');

        $to_copy = array_filter($backup_sets, function ($elem) use ($hash) {
            return $elem['hash'] == $hash;
        });
        if (empty($to_copy)) {
            throw new \InvalidArgumentException('Could not find backup-set with hash `' . $hash . '`');
        }

        $shell = $this->getHostConfig()->shell();
        $files = [];
        foreach ($to_copy as $elem) {
            if (!in_array($elem['type'], $what)) {
                continue;
            }
            if ($shell->getFile(
                $this->getHostConfig()['backupFolder'] . '/' . $elem['file'],
                getcwd(),
                $context
            )) {
                $files[] = [
                    'type' => $elem['type'],
                    'file' => $elem['file'],
                ];
            }
        }


        if (count($files) > 0) {
            $io = new SymfonyStyle($input, $output);
            $io->title('Copied backup-set:');
            $io->table(
                ['Type', 'File'],
                $files
            );
        }

        return 0;
    }

}