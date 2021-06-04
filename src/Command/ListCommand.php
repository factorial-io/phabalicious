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
use Symfony\Component\Console\Helper\TableSeparator;
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

        $host_config_names = array_keys(
            array_filter(
                $this->configuration->getAllHostConfigs(),
                function ($host_config) {
                    return empty($host_config['inheritOnly']);
                }
            )
        );


        $io = new SymfonyStyle($input, $output);
        $io->title('List of found host-configurations:');
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->showDetails($io, $host_config_names);
        } else {
            $io->listing($host_config_names);
        }

        return 0;
    }

    /**
     * @param SymfonyStyle $io
     * @param array $host_config_names
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function showDetails(SymfonyStyle $io, array $host_config_names): void
    {
        $rows = [];
        foreach ($host_config_names as $ndx => $config_name) {
            $host = $this->configuration->getHostConfig($config_name);
            $rows[] = [
                'name' => $host->getConfigName(),
                'public urls' => implode("\n", $host->getPublicUrls()),
                'description' => $host->getDescription()
            ];
            if ($ndx !== count($host_config_names) - 1) {
                $rows[] = new TableSeparator();
            }
        }

        $io->table(['config name', 'public urls', 'description'], $rows);
    }
}
