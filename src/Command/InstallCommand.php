<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InstallCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('install')
            ->setDescription('Install an instance')
            ->setHelp('Runs all tasks necessary to install an instance on a existing code-base');
        $this->addOption(
            'skip-reset',
            null,
            InputOption::VALUE_OPTIONAL,
            'Skip the reset-task if set to true',
            false
        );
        $this->addOption(
            'yes',
            'y',
            InputOption::VALUE_OPTIONAL,
            'Skip confirmation step, install without question',
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

        $context = new TaskContext($this, $input, $output);

        $host_config = $this->getHostConfig();
        if ($host_config['supportsInstalls'] == false) {
            throw new \InvalidArgumentException('This configuration disallows installs!');
        }

        if (!$input->getOption('yes')) {
            if (!$context->io()->confirm(sprintf(
                'Install new database for configuration `%s`?',
                $this->getHostConfig()['configName']
            ), false)) {
                return 1;
            }
        }


        $next_tasks = $input->getOption('skip-reset') ? [] : ['reset'];

        $context->io()->comment('Installing new app for `' . $this->getHostConfig()['configName']. '`');

        try {
            $this->getMethods()->runTask('install', $this->getHostConfig(), $context, $next_tasks);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }
}
