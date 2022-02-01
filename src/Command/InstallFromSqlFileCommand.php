<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Method\DrushMethod;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallFromSqlFileCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('install:from-sql-file')
            ->setDescription('Install an instance from an existing sql file')
            ->setHelp('Runs all tasks necessary to install an instance from a sql-dump');
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
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        $context = $this->getContext();
        // We are not interested in a config import when using drupal.
        $context->set(DrushMethod::SKIP_CONFIGURATION_IMPORT, true);

        if ($result = $this->runCommand(
            'install',
            [
                '--skip-reset' => '1',
            ],
            $input,
            $output
        )) {
            return $result;
        }

        if ($result = $this->runCommand(
            'restore:sql-from-file',
            [
                'file' => $input->getArgument('file'),
                '--skip-drop-db' => $input->getOption('skip-drop-db'),
            ],
            $input,
            $output
        )) {
            return $result;
        }

        return $this->runCommand(
            'reset',
            [],
            $input,
            $output
        );
    }
}
