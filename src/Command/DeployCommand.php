<?php /** @noinspection PhpRedundantCatchClauseInspection */

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
use Phabalicious\Method\GitMethod;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommand extends BaseCommand
{
    protected static $defaultName = 'about';

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('deploy')
            ->setDescription('Deploys the current application.')
            ->addArgument(
                'branch',
                InputArgument::OPTIONAL,
                'Branch to deploy, if not set, the host-config is used'
            )
            ->addOption(
                'arguments',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Pass optional arguments',
                []
            )
            ->setHelp('Deploys the current application to a given host-configuration.');
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
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();

        // Override branch in config.
        $branch = $input->getArgument('branch');
        if (!empty($branch)) {
            $this->getHostConfig()->setProperty(GitMethod::BRANCH_KEY, $branch);
        }

        if ($this->getHostConfig()->getProperty(GitMethod::BRANCH_KEY)) {
            $context->io()->comment(sprintf(
                'Deploying branch `%s` with config `%s` ...',
                $this->getHostConfig()->getProperty(GitMethod::BRANCH_KEY),
                $this->getHostConfig()->getConfigName()
            ));
        }

        if ($this->getHostConfig()['backupBeforeDeploy']) {
            $this->runBackup($input, $output);
        }

        try {
            $this->getMethods()->runTask('deploy', $this->getHostConfig(), $context, ['reset']);
            $context->io()->success(sprintf("%s deployed successfully!", $this->getHostConfig()->getLabel()));
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }

    private function runBackup(InputInterface $original_input, OutputInterface $output)
    {
        return $this->runCommand('backup', [ 'what' => ['db'] ], $original_input, $output);
    }
}
