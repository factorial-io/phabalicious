<?php

namespace Phabalicious\Command;

use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

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
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                'The name of the file to store the output to.'
            )
            ->addOption(
                'format',
                null,
                InputOption::VALUE_OPTIONAL,
                'The format the found value should be used for output'
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

        $format = strtolower($input->getOption('format') ?: 'plain');
        if (!in_array($format, ['plain', 'json', 'yaml'])) {
            throw new \RuntimeException(sprintf(
                'Unknown value `%s` for format-option, only `plain`, `json` or `yaml` are supported!',
                $format
            ));
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
        $value = $this->getConfiguration()->getPasswordManager()->resolveSecrets($value);

        $result = '';
        switch ($format) {
            case 'json':
                $result = json_encode($value, JSON_PRETTY_PRINT);
                break;
            case 'yaml':
                $result = Yaml::dump($value, 5, 2);
                break;
            default:
                if (is_array($value)) {
                    $result = json_encode($value, JSON_PRETTY_PRINT);
                } else {
                    $result = $value;
                }
        }
        if ($output_file_name = $input->getOption('output')) {
            file_put_contents($output_file_name, $result);
        } else {
            $output->writeln($result);
        }
        return 0;
    }
}
