<?php

namespace Phabalicious\Command;

use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetPropertyCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();

        $this->setAliases(['getProperty']);
        $this
            ->setName('get:property')
            ->setDescription('Get a property from a host-configuration')
            ->setHelp('Get a property from a host-configuration')
            ->addArgument(
                'property',
                InputArgument::REQUIRED,
                'The name of the property to get. Use dot-syntax to get sub-properties'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $property = $input->getArgument('property');
        $value = Utilities::getProperty(
            $this->getHostConfig(),
            $property,
            null
        );
        if (is_null($value)) {
            $output->writeln('<error>Could not get property `' . $property . '`!</error>');
            return 1;
        }
        if (is_array($value)) {
            $output->writeln(json_encode($value, JSON_PRETTY_PRINT));
        } else {
            $output->writeln($value);
        }
        return 0;
    }

}