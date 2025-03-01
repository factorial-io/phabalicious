<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\AppDefaultStages;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppDestroyCommand extends AppBaseCommand
{
    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory, $name = null)
    {
        parent::__construct($configuration, $method_factory, $name);
    }

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('app:destroy')
            ->setDescription('Destroys an existing app and removes it completely')
            // @phpcs:disable
            ->setHelp('
Destroys an existing application. Phabalicious executes a list
of socalled stages, e.g.

- spin down the application
- delete the containers/ pods
- delete the code base

Using blueprints makes it possible to delete an exisiting application which is
derived from a single variable (most often the branch name). Useful for sth like
feature-based deployments.

For more information about destroying existing apps, please visit
<href=https://docs.phab.io/app-create-destroy.html>the official documentation</> (https://docs.phab.io/app-create-destroy.html)


Examples:
<info>phab -cconfig app:destroy</info>
<info>phab --blueprint=some-blueprint --config=config app:destroy</info>
        ');
        // @phpcs:enable
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
        $host_config = $this->getHostConfig();

        $this->configuration->getMethodFactory()->runTask('appCheckExisting', $host_config, $context);

        $install_dir = $context->getResult('installDir', false);
        $install_name = $context->getResult('installName', $install_dir);
        $outer_shell = null;
        if ($install_dir) {
            /** @var ShellProviderInterface $outer_shell */
            $outer_shell = $context->getResult('outerShell', $host_config->shell());
            $lock_file = $install_dir.'/.projectCreated';
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
            $context->io()->success(sprintf('App `%s` destroyed!', $host_config->getConfigName()));
        } else {
            $this->configuration->getLogger()->warning(sprintf('Could not find app `%s` at `%s`!', $host_config->getConfigName(), $install_name));
        }

        return $context->getResult('exitCode', 0);
    }
}
