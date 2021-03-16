<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('backup')
            ->setDescription('Backups all data of an application')
            ->setHelp('Backups all data of an application');
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to backup, allowed are `db` and `files`, if nothing is set, everything will be backed up',
            ['files', 'db']
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
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

        $context = $this->getContext();
        $context->set('what', array_map(function ($elem) {
            return trim(strtolower($elem));
        }, $input->getArgument('what')));

        $context->setResult('basename', [
           $this->getHostConfig()['configName'],
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
