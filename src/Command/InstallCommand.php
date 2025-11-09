<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('install')
            ->setDescription('Install an instance')
            ->setHelp('
Runs all tasks necessary to install an instance on an existing code-base.

This command installs a new instance (e.g., installing a database, setting up configuration).

Behavior:
- The configuration must have supportsInstalls not set to false
  (configurations can explicitly disallow installs)
- Prompts for confirmation before installing (unless --force is used)
- After installation, runs the reset task (unless --skip-reset is specified)

You can configure install options in your fabfile.yaml:
installOptions:
  distribution: thunder
  locale: es

Examples:
<info>phab --config=myconfig install</info>
<info>phab --config=myconfig install --force</info>
<info>phab --config=myconfig install --skip-reset</info>
            ');
        $this->addOption(
            'skip-reset',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip the reset-task if set to true',
            false
        );
    }

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $host_config = $this->getHostConfig();

        if (false == $host_config['supportsInstalls']) {
            throw new \InvalidArgumentException('This configuration disallows installs!');
        }

        if (!$this->hasForceOption($input)) {
            if (!$context->io()->confirm(sprintf(
                'Install new database for configuration `%s`?',
                $this->getHostConfig()->getConfigName()
            ), false)) {
                return 1;
            }
        }

        $next_tasks = $input->getOption('skip-reset') ? [] : ['reset'];

        $context->io()->comment('Installing new app for `'.$this->getHostConfig()->getConfigName().'`');

        try {
            $this->getMethods()->runTask('install', $this->getHostConfig(), $context, $next_tasks);
            $context->io()->success(sprintf('%s installed successfully!', $this->getHostConfig()->getLabel()));
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }
}
