<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\HostConfigurationCategory;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableCell;
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
            ->setHelp('
Displays a list of all host configurations from the fabfile.

This command lists all available host configurations defined in your fabfile,
excluding hidden configurations and inherit-only configurations.

Behavior:
- Reads all host configurations from the fabfile
- Filters out hidden hosts (hidden: true) and inherit-only hosts
- Groups hosts by categories if categories are defined
- Shows configuration names and public URLs by default
- With increased verbosity (-v), shows descriptions and all public URLs
- Displays project name and description if defined in fabfile

Configuration categories help organize hosts (e.g., "Development", "Production").
Invalid configurations are shown in a separate "Configurations with validation errors" section.

Verbosity levels:
- Normal: Shows host names and main public URL
- Verbose (-v): Shows host descriptions and all public URLs with links

Examples:
<info>phab list:hosts</info>
<info>phab list:hosts -v</info>              # Show detailed listing with descriptions
<info>phab --fabfile=other.yaml list:hosts</info>
            ');

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
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->readConfiguration($input);

        $host_config_names = array_keys(
            array_filter(
                $this->configuration->getAllHostConfigs()->getValue(),
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
            $this->showListing($io, $host_config_names, true);
        } else {
            $this->showListing($io, $host_config_names, false);
        }

        return 0;
    }


    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param array $host_config_names
     * @param bool $detailed
     */
    protected function showListing(SymfonyStyle $io, array $host_config_names, bool $detailed)
    {
        $hosts = $this->getHostsByCategories($host_config_names);
        $has_categories = count($hosts) > 1;
        foreach ($hosts as $category_id => $configs) {
            $category = HostConfigurationCategory::get($category_id);
            if ($has_categories) {
                $io->section($category->getLabel());
            }
            foreach ($configs as $config) {
                if (is_string($config)) {
                    $io->writeln(sprintf(' * %s', $config));
                } else {
                    /** @var HostConfig $config */
                    if (!$detailed) {
                        $io->writeln(sprintf(
                            ' ‣ %s  <info>%s</info>',
                            $config->getConfigName(),
                            $config->getMainPublicUrl()
                        ));
                    } else {
                        $io->writeln(sprintf(
                            ' ‣ <options=bold>%s</>',
                            $config->getConfigName()
                        ));
                        $newline = false;
                        if ($config->getDescription()) {
                            $newline = true;
                            $lines = explode("\n", $config->getDescription());
                            foreach ($lines as $line) {
                                $io->writeln(sprintf('   %s', $line)) ;
                            }
                        }
                        array_map(function ($url) use ($io, $newline) {
                            $newline = true;
                            $io->writeln(sprintf('   → <href=%s><info>%s</>', $url, $url));
                        }, $config->getPublicUrls());
                        if ($newline) {
                            $io->writeln("");
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array $host_config_names
     *
     * @return array
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function getHostsByCategories(array $host_config_names): array
    {
        $categories = [];
        foreach ($host_config_names as $ndx => $config_name) {
            try {
                $host = $this->configuration->getHostConfig($config_name);
                $categories[$host->getCategory()->getId()][] = $host;
            } catch (ValidationFailedException $exception) {
                $error_category = HostConfigurationCategory::getOrCreate([
                    'id' => 'zzz',
                    'label' => 'Configurations with validation errors'
                ]);
                $categories[$error_category->getId()][] = sprintf(
                    "%s  <error> Invalid config </error>",
                    $config_name
                );
            }
        }
        ksort($categories);
        return $categories;
    }
}
