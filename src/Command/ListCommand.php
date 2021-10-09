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
     *
     * @return int
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->readConfiguration($input);

        $host_config_names = array_keys(
            array_filter(
                $this->configuration->getAllHostConfigs(),
                function ($host_config) {
                    return empty($host_config['hidden']) && empty($host_config['inheritOnly']);
                }
            )
        );


        $io = new SymfonyStyle($input, $output);
        if ($description = $this->configuration->getSetting('description')) {
            $io->title($this->configuration->getSetting('name'));
            $io->block($description, null, 'fg=blue');
            $io->writeln("");
        }
        $io->title(sprintf(
            'Available configurations for %s',
            $this->configuration->getSetting('name', 'this project')
        ));
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->showDetails($io, $host_config_names);
        } else {
            $this->showListing($io, $host_config_names);
        }

        return 0;
    }

    /**
     * @param SymfonyStyle $io
     * @param array $host_config_names
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function showDetails(SymfonyStyle $io, array $host_config_names): void
    {
        $rows = [];
        foreach ($host_config_names as $ndx => $config_name) {
            try {
                $host = $this->configuration->getHostConfig($config_name);
                $rows[] = [
                    'name' => $host->getConfigName(),
                    'public urls' => sprintf("<info>%s</info>", implode("</info>\n<info>", $host->getPublicUrls())),
                    'description' => $host->getDescription(),
                ];
            } catch (ValidationFailedException $exception) {
                $rows[] = [
                    'name' => $config_name,
                    'public urls' => '',
                    'description' => "<error> Could not validate configuration </error>"
                ];
            }
            if ($ndx !== count($host_config_names) - 1) {
                $rows[] = new TableSeparator();
            }
        }

        $io->table(['config name', 'public urls', 'description'], $rows);
    }

    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param array $host_config_names
     *
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function showListing(SymfonyStyle $io, array $host_config_names)
    {
        $rows = [];
        foreach ($host_config_names as $ndx => $config_name) {
            try {
                $host = $this->configuration->getHostConfig($config_name);
                $url = $host->getMainPublicUrl();
                $rows[] = $url ? sprintf("%s  <info>%s</info>", $host->getConfigName(), $url) : $host->getConfigName();
            } catch (ValidationFailedException $exception) {
                $rows[] = sprintf("%s  <error> Invalid config </error>", $config_name);
            }
        }
        $io->listing($rows);
    }
}
