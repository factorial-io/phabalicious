<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListBlueprintsCommand extends BaseOptionsCommand
{
    protected function configure(): void
    {
        $this
            ->setName('list:blueprints')
            ->setDescription('List all blueprints')
            ->setHelp('Displays a list of all found blueprints from a fabfile');

        parent::configure();
    }

    /**
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
