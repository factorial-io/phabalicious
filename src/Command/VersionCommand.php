<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('version')
            ->setDescription('Get the current installed version.')
            ->setHelp('
Gets the current installed version of the application on a specific host.

This command retrieves version information from the configured host.
The version information depends on the method implementation and might include:
- Application version number
- Git commit hash or tag
- Custom version identifiers

Behavior:
- Runs the "version" task on the specified host configuration
- Displays version information if successfully retrieved
- Shows an error message if version cannot be determined

Examples:
<info>phab --config=myconfig version</info>
<info>phab --config=production version</info>
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

        $this->getMethods()->runTask('version', $this->getHostConfig(), $context);

        if ($version = $context->getResult('version')) {
            $context->io()->success($version);

            return 0;
        }

        $context->io()->error('Could not determine the current version');

        return 1;
    }
}
