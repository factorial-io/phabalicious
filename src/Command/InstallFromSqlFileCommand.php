<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Method\DrushMethod;
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
            ->setDescription('Install an instance from an existing sql file');
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
        $this->addOption(
            'skip-reset',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip the reset-task if set to true',
            false
        );

        $this->setHelp('
This command will install an application from a local sql-file, by running the
three standalone commands <info>install</info>, <info>restore:from-sql-file</info>
and <info>reset</info>. It will skip any configuration-import while running
install to speed things up.

Passing the option <info>skip-drop-db</info> will keep the existing DB intact,
but this might result in problems while importing the SQL-file, so use with
care.

Passing the <info>skip-reset</info> option will keep the app in the state
derived from the sql dump, without running the reset-task.

Examples:
<info>phab install:from-sql-file my/sql.tgz --config mbb</info>

        ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int

     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }
        $context = $this->getContext();
        // We are not interested in a config import during install
        // when using drupal, as we are running `reset` as a last step.
        $context->set(DrushMethod::SKIP_CONFIGURATION_IMPORT, true);

        if ($result = $this->runCommand(
            'install',
            [
                '--skip-reset' => '1',
            ],
            $input,
            $output,
            $context
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
            $output,
            $context
        )) {
            return $result;
        }

        if (!$input->getOption('skip-reset')) {
            return $this->runCommand(
                'reset',
                [],
                $input,
                $output,
                $context
            );
        }
        return $result;
    }
}
