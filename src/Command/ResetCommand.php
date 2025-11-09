<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetCommand extends BaseCommand
{
    protected static $defaultName = 'reset';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('reset')
            ->setDescription('Resets the current application.')
            ->setHelp('
Resets the current application to a clean, working state.

This command performs reset/update tasks on an installed application to ensure
it is in a consistent, working state. The exact actions depend on the methods
configured for your host, but typically include:

Common reset tasks:
- Clear caches
- Run database updates/migrations
- Rebuild configuration
- Import/update configuration from code
- Reindex search
- Compile assets

Behavior:
- Runs the "reset" task defined by your host configuration\'s methods
- Each method (e.g., drupal, laravel, script) can contribute reset steps
- Shows success message when reset completes
- Typically run after deployments, installations, or copying data

This command is automatically run after:
- deploy command (unless --skip-reset is used)
- install command (unless --skip-reset is used)
- copy-from command (unless --skip-reset is used)

Examples:
<info>phab --config=myconfig reset</info>
<info>phab reset</info>  # Uses default config (ddev)
            ');
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

        try {
            $this->getMethods()->runTask('reset', $this->getHostConfig(), $context);
            $context->io()->success(sprintf('%s resetted successfully!', $this->getHostConfig()->getLabel()));
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }
}
