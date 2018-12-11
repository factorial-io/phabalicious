<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\AppDefaultStages;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppCreateCommand extends AppBaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('app:create')
            ->setDescription('Creates a new app from vthe code-base of a project')
            ->setHelp('Creates a new app from the code-base of a project');

        $this->addOption(
            'copyFrom',
            null,
            InputOption::VALUE_OPTIONAL,
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
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        if ($copy_from = $input->getOption('copyFrom')) {
            // Make sure config exists.
            $copy_from = $this->getConfiguration()->getHostConfig($copy_from);
        }

        $context = new TaskContext($this, $input, $output);
        $host_config = $this->getHostConfig();

        $this->configuration->getMethodFactory()->runTask('appCheckExisting', $host_config, $context);

        /** @var ShellProviderInterface $outer_shell */
        $outer_shell = $context->getResult('outerShell', $host_config->shell());
        $lock_file = $context->getResult('installDir', $host_config['rootFolder']) . '/.projectCreated';
        $app_exists = $outer_shell->exists($lock_file);

        $context->set('outerShell', $outer_shell);
        $context->set('installDir', $context->getResult('installDir'));
        $context->clearResults();

        if ($app_exists) {
            $this->configuration->getLogger()->notice('Existing app found, deploying instead!');

            $stages = $this->configuration->getSetting(
                'appStages.deploy',
                AppDefaultStages::DEPLOY
            );
            $this->executeStages($stages, 'appCreate', $context, 'Creating app');
            $this->runCommand('deploy', [], $input, $output);
        } else {
            $stages = $this->configuration->getSetting(
                'appStages.create',
                AppDefaultStages::CREATE
            );

            $this->executeStages($stages, 'appCreate', $context, 'Creating app');

            if ($copy_from) {
                $this->runCommand('copy-from', [ 'from' => $copy_from['configName'] ], $input, $output);
            } else {
                $this->runCommand('reset', [], $input, $output);
            }
        }

        return $context->getResult('exitCode', 0);
    }

}
