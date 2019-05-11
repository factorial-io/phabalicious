<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Exception\ValidationFailedException;
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
            'copy-from',
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
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws MissingHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     * @throws ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        if ($copy_from = $input->getOption('copy-from')) {
            // Make sure config exists.
            $copy_from = $this->getConfiguration()->getHostConfig($copy_from);
        }

        $context = new TaskContext($this, $input, $output);
        $host_config = $this->getHostConfig();

        $this->configuration->getMethodFactory()->runTask('appCheckExisting', $host_config, $context);

        /** @var ShellProviderInterface $outer_shell */
        $outer_shell = $context->getResult('outerShell', $host_config->shell());

        $install_dir = $context->getResult('installDir', $host_config['rootFolder']);
        if ($outer_shell->exists($install_dir) && $input->getOption('force') === false) {
            $continue = $context->io()->confirm('Target directory exists', false);
            if (!$continue) {
                $context->io()->warning(sprintf(
                    'Stopping, as target-folder `%s` already exists on `%s`.',
                    $install_dir,
                    $host_config['configName']
                ));
                return 1;
            }
        }
        $lock_file =  $install_dir . '/.projectCreated';
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
