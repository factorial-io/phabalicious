<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppUpdateCommand extends BaseCommand
{
    protected static $defaultName = 'app:update';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('app:update')
            ->setDescription('Updates the code-base of a project')
            ->setHelp('
Updates the code-base to the latest changes.

When using the drush method, Drupal core will be updated to the latest version
(but only if composer is not used). If using composer, then composer will be
used to update the existing code.

Examples:
<info>phab --config=myconfig app:update</info>
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
            $this->getMethods()->runTask('appUpdate', $this->getHostConfig(), $context);
        } catch (EarlyTaskExitException $e) {
            return 1;
        }

        return $context->getResult('exitCode', 0);
    }
}
