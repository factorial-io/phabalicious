<?php

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = new TaskContext($this, $input, $output);

        $this->getMethods()->runTask('version', $this->getHostConfig(), $context);

        if ($version = $context->get('version')) {
            $output->writeln($version);
            return 0;
        }

        $output->writeln('<error>Could not determine the current version</error>');
        return 1;

    }


}