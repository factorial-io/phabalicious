<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PlatformCommand extends BaseCommand
{
    protected static $defaultName = 'about';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('platform')
            ->setDescription('Runs platform')
            ->setHelp('
Runs Platform.sh CLI commands against the configured host.

This command provides integration with Platform.sh, allowing you to run
platform CLI commands on hosts that are deployed on Platform.sh infrastructure.

Behavior:
- Requires host configuration with platform.sh integration enabled
- Executes the platform command in the context of the configured host
- Passes all arguments to the platform CLI
- Returns the exit code from the platform command

The host configuration must have platform.sh properly configured,
typically including project ID, environment, and authentication.

Arguments:
- <platform>: The platform.sh command and arguments to run

Examples:
<info>phab --config=myconfig platform environment:info</info>
<info>phab --config=production platform app:list</info>
<info>phab --config=myconfig platform domain:list</info>
<info>phab --config=myconfig platform activity:list</info>
            ');
        $this->addArgument(
            'platform',
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'The platform-command to run'
        );
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
        $context->set('command', implode(' ', $input->getArgument('platform')));

        try {
            $this->getMethods()->runTask('platform', $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }
}
