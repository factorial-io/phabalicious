<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestoreCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('restore')
            ->setDescription('Restores a given backup-set')
            ->setHelp('Restores a given backup-set for a given configuration');
        $this->addArgument(
            'hash',
            InputArgument::REQUIRED,
            'The hash of the backup-set to restore'
        );
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to restore, if ommitted files and db will be restored',
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

        $context = $this->createContext($input, $output);
        $context->set('what', array_map(function ($elem) {
            return trim(strtolower($elem));
        }, $input->getArgument('what')));

        $hash = $input->getArgument('hash');

        $this->getMethods()->runTask('listBackups', $this->getHostConfig(), $context);
        $backup_sets = $context->getResult('files');

        $to_restore = array_filter($backup_sets, function ($elem) use ($hash) {
            return $elem['hash'] == $hash;
        });
        if (empty($to_restore)) {
            throw new \InvalidArgumentException('Could not find backup-set with hash `' . $hash . '`');
        }

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
