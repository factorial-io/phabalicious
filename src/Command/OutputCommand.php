<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Dumper;

class OutputCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('output')
            ->setDescription('Outputs the configurarion as yaml')
            ->setHelp('Outputs the configuration as yaml');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $input->getOption('config');
        $blueprint = $input->getOption('blueprint');
        if (empty($blueprint)) {
            throw new \InvalidArgumentException('The required option --blueprint is not set or is empty');
        }

        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $template = $this->getConfiguration()->getBlueprints()->getTemplate($config);
        $data = $template->expand($blueprint);
        $data = ['hosts' => [
            $data['configName'] => $data
        ]];

        $dumper = new Dumper(2);

        $io = new SymfonyStyle($input, $output);
        $io->title('Output of applied blueprint `' . $config . '`');
        $io->block($dumper->dump($data, 10, 2));


        return 0;
    }


}