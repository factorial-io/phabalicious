<?php

/** @noinspection PhpRedundantCatchClauseInspection */

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
            ->setHelp('Runs all tasks necessary to install an instance runs a copy-from from another '.
                'instance to get all data.');
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
        if ('from' == $argumentName) {
            return $this->configuration->getAllHostConfigs()->getKeys();
        } elseif ('what' == $argumentName) {
            return [
                'db',
                'files',
            ];
        }

        return [];
    }

    /**
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
