<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListBlueprintsCommand extends Command
{

    private $configuration;

    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory, $name = null)
    {
        $this->configuration = $configuration;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('list:blueprints')
            ->setDescription('List all blueprints')
            ->setHelp('Displays a list of all found blueprints from a fabfile');
        $this
            ->addOption(
                'fabfile',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Override with a custom fabfile'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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
    }


}