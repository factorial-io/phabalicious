<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListCommand extends BaseOptionsCommand
{

    protected function configure()
    {
        $this
            ->setName('list:hosts')
            ->setDescription('List all configurations')
            ->setHelp('Displays a list of all found confgurations from a fabfile');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->readConfiguration($input);

        $hosts = array_keys(
            array_filter(
                $this->configuration->getAllHostConfigs(),
                function ($host_config) {
                    return empty($host_config['inheritOnly']);
                }
            )
        );

        $io = new SymfonyStyle($input, $output);
        $io->title('List of found host-configurations:');
        $io->listing($hosts);

        return 0;
    }
}
