<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends BaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('version')
            ->setDescription('Get the current installed version.')
            ->setHelp('Gets the current installed version on a specific host');
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
