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

class ListBlueprintsCommand extends BaseOptionsCommand
{

    protected function configure()
    {
        $this
            ->setName('list:blueprints')
            ->setDescription('List all blueprints')
            ->setHelp('Displays a list of all found blueprints from a fabfile');

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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fabfile = !empty($input->getOption('fabfile')) ? $input->getOption('fabfile') : '';
        $this->configuration->readConfiguration(getcwd(), $fabfile);

        $blueprints = array_map(function ($key) {
            $a = explode(':', $key);
            return array_pop($a);
        }, array_keys($this->configuration->getBlueprints()->getTemplates()));

        $io = new SymfonyStyle($input, $output);
        $io->title('List of found blueprints:');
        $io->listing($blueprints);

        return 0;
    }
}
