<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setHelp('Deploys the current application to a given host-configuration.');
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
        if ($input->hasArgument('branch')) {
            $branch = $input->getArgument('branch');
            $context->set('branch', $branch);
        }

        if ($this->getHostConfig()['backupBeforeDeploy']) {
            $this->runBackup($input, $output);
        }

        try {
            $this->getMethods()->runTask('deploy', $this->getHostConfig(), $context, ['reset']);
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
