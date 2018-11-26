<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\HostType;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
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

        $host_config = $this->getHostConfig();
        if ($host_config->isType(HostType::PROD) || !$host_config['supportsInstalls']) {
            throw new \InvalidArgumentException('This configuration disallows installs!');
        }

        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'Install new database for configuration `' . $this->getHostConfig()['configName'] . '`? ',
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $context = new TaskContext($this, $input, $output);

        $next_tasks = $input->getOption('skip-reset') ? [] : ['reset'];
        try {
            $this->getMethods()->runTask('install', $this->getHostConfig(), $context, $next_tasks);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }

}
