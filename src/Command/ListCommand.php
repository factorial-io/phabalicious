<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\HostConfigurationCategory;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ListCommand extends BaseOptionsCommand
{
    protected function configure(): void
    {
        $this
            ->setName('list:hosts')
            ->setDescription('List all configurations')
            ->setHelp('Displays a list of all found confgurations from a fabfile');

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
            $io->writeln('');
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
                    /* @var HostConfig $config */
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
                                $io->writeln(sprintf('   %s', $line));
                            }
                        }
                        array_map(function ($url) use ($io, $newline) {
                            $newline = true;
                            $io->writeln(sprintf('   → <href=%s><info>%s</>', $url, $url));
                        }, $config->getPublicUrls());
                        if ($newline) {
                            $io->writeln('');
                        }
                    }
                }
            }
        }
    }

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
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
                    'label' => 'Configurations with validation errors',
                ]);
                $categories[$error_category->getId()][] = sprintf(
                    '%s  <error> Invalid config </error>',
                    $config_name
                );
            }
        }
        ksort($categories);

        return $categories;
    }
}
