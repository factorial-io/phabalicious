<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\AppDefaultStages;
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
            ->setDescription('Creates a new app from the code-base of a project');

        $this->addOption(
            'copy-from',
            null,
            InputOption::VALUE_OPTIONAL,
            false
        );
        // @phpcs:disable
        $this->setHelp('
Creates a new application from an existing config. Phabalicious executes a list
of socalled stages, e.g.

- preparing the destination,
- install the current code base,
- start the application,
- install its dependencies and
- install the app.

If phab detects an already created app, it will instead deploy the current
application.

Using blueprints makes it possible to create a new application which is derived
from a single variable (most often the branch name) to create a new application.
Useful for sth like feature-based deployments.

For more information about creating new apps, please visit
<href=https://docs.phab.io/app-create-destroy.html>the official documentation</> (https://docs.phab.io/app-create-destroy.html)


Examples:
<info>phab -cconfig app:create</info>
<info>phab --blueprint=some-blueprint --config=config app:create</info>
        ');
        // @phpcs:enable
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        if ($copy_from = $input->getOption("copy-from")) {
            // Make sure config exists.
            $copy_from = $this->getConfiguration()->getHostConfig($copy_from);
        }

        /** @var \Phabalicious\Configuration\HostConfig $copy_from */
        $context = $this->getContext();
        $host_config = $this->getHostConfig();

        $this->configuration
            ->getMethodFactory()
            ->runTask("appCheckExisting", $host_config, $context);
        $outer_shell = false;

        $install_dir = $context->getResult("installDir", false);
        if ($install_dir) {
            /** @var ShellProviderInterface $outer_shell */
            $outer_shell = $context->getResult(
                "outerShell",
                $host_config->shell()
            );
            if ($outer_shell->exists($install_dir) &&
                $input->getOption("force") === false
            ) {
                $continue = $context
                    ->io()
                    ->confirm("Target directory exists", false);
                if (!$continue) {
                    $context
                        ->io()
                        ->warning(
                            sprintf(
                                "Stopping, as target-folder `%s` already exists on `%s`.",
                                $install_dir,
                                $host_config->getConfigName()
                            )
                        );
                    return 1;
                }
            }
            $lock_file = $install_dir . "/.projectCreated";
            $app_exists = $outer_shell->exists($lock_file);
        } else {
            $app_exists = $context->getResult("appExists", null);
            if (is_null($app_exists)) {
                throw new \InvalidArgumentException(
                    "No result for `appExists`, stopping execution!"
                );
            }
        }

        $context->set("outerShell", $outer_shell);
        $context->set("installDir", $context->getResult("installDir"));
        $context->clearResults();

        if ($app_exists && !$this->hasForceOption($input)) {
            $this->configuration
                ->getLogger()
                ->notice("Existing app found, deploying instead!");

            $stages = $this->configuration->getSetting(
                "appStages.deploy",
                AppDefaultStages::DEPLOY
            );
            $this->executeStages(
                $stages,
                "appCreate",
                $context,
                "Creating app"
            );
            $this->runCommand("deploy", [], $input, $output);
        } else {
            $stages = $this->configuration->getSetting(
                "appStages.create",
                AppDefaultStages::CREATE
            );

            $this->executeStages(
                $stages,
                "appCreate",
                $context,
                "Creating app"
            );

            if ($copy_from) {
                $this->runCommand(
                    "copy-from",
                    ["from" => $copy_from->getConfigName()],
                    $input,
                    $output
                );
            } else {
                $this->runCommand("reset", [], $input, $output);
            }
        }

        return $context->getResult("exitCode", 0);
    }
}
