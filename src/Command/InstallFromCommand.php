<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallFromCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('install:from')
            ->setDescription('Install an instance and get files and db from another instance')
            ->setHelp('
Installs a new instance and copies data from another existing instance.

This is a convenience command that combines "install" and "copy-from" into
a single operation. It first installs a fresh instance, then copies database
and/or files from the source instance.

Behavior:
- Runs the install command with --skip-reset
- Then runs copy-from to get data from the source instance
- The source instance must have supportsCopyFrom set to true
- After copying, runs the reset task (unless skipped by copy-from)

This is ideal for creating a new environment based on an existing one,
such as creating a local development environment from staging/production.

Arguments:
- <from>: Name of the source host configuration to copy from
- <what>: What to copy (optional, defaults to both db and files)
         Valid values: db, files
         Can specify one or both

Examples:
<info>phab --config=local install:from production</info>
<info>phab --config=dev install:from staging db</info>       # Only copy database
<info>phab --config=test install:from live files</info>      # Only copy files
<info>phab --config=local installFrom production</info>      # Using alias
            ');
        $this->addArgument(
            'from',
            InputArgument::REQUIRED,
            'From which instance to copy from'
        );
        $this->addArgument(
            'what',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'What to to copy, allowed are `db` and `files`, if nothing is set, everything will be copied',
            ['files', 'db']
        );

        $this->setAliases(['installFrom']);
    }

    public function completeArgumentValues($argumentName, CompletionContext $context): array
    {
        if ($argumentName == 'from') {
            return $this->configuration->getAllHostConfigs()->getKeys();
        } elseif ($argumentName == 'what') {
            return [
                'db',
                'files'
            ];
        }
        return [];
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

        return $this->runCommand(
            'copyFrom',
            [
                'what' => $input->getArgument('what'),
                'from' => $input->getArgument('from'),
            ],
            $input,
            $output
        );
    }
}
