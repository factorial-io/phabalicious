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

class AppDestroyCommand extends AppBaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('app:destroy')
            ->setDescription('Destroys an existing app and removes it completely')
            ->setHelp('Destroys an existing app and removes it completely');
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

        $context = $this->getContext();
        $host_config = $this->getHostConfig();

        $this->configuration->getMethodFactory()->runTask('appCheckExisting', $host_config, $context);

        $install_dir = $context->getResult('installDir', false);
        $outer_shell = null;
        if ($install_dir) {
            /** @var ShellProviderInterface $outer_shell */
            $outer_shell = $context->getResult('outerShell', $host_config->shell());
            $lock_file = $install_dir . '/.projectCreated';
            $app_exists = $outer_shell->exists($lock_file);
        } else {
            $app_exists = $context->getResult('appExists', false);
        }

        $context->set('outerShell', $outer_shell);
        $context->set('installDir', $context->getResult('installDir'));
        $context->clearResults();

        if ($app_exists) {
            $stages = $this->configuration->getSetting(
                'appStages.destroy',
                AppDefaultStages::DESTROY
            );
            $this->executeStages($stages, 'appDestroy', $context, 'Destroying app');
            if ($install_dir) {
                $outer_shell->run(sprintf('sudo rm -rf %s', $install_dir));
            }
        } else {
            $this->configuration->getLogger()->warning(sprintf('Could not find app at `%s`!', $install_dir));
        }

        return $context->getResult('exitCode', 0);
    }
}
