<?php

namespace Phabalicious\ShellCompletion;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Facade;
use Psr\Log\NullLogger;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class FishShellCompletionContext extends CompletionContext
{
    /** @var ConfigurationService */
    protected $configuration;

    protected $configName;

    public function __construct(ConfigurationService $configuration, Application $application, $commandline)
    {
        $this->configuration = $configuration;
        $this->commandLine = $commandline;

        $input_definition = new UnvalidatedInputDefinition([
            new InputOption(
                'fabfile',
                'f',
                InputOption::VALUE_OPTIONAL
            ),
            new InputOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL
            ),
            new InputOption(
                'offline',
                null,
                InputOption::VALUE_OPTIONAL
            ),
        ]);

        // Copy the app-options.
        foreach ($application->getDefinition()->getOptions() as $option) {
            $input_definition->addOption($option);
        }

        $input = new ArgvInput(explode(' ', $commandline), $input_definition);

        $fabfile = !empty($input->getOption('fabfile')) ? $input->getOption('fabfile') : '';
        $this->configuration->setOffline(true);
        try {
            $this->configuration->readConfiguration(getcwd(), $fabfile);
        } catch (\Exception $e) {
            $this->configuration = null;
        }

        if ($input->hasOption('config')) {
            $this->configName = $input->getOption('config');
        }
    }

    public function getHostConfig()
    {
        if (!$this->configuration || !$this->configName) {
            return null;
        }
        try {
            return $this->configuration->getHostConfig($this->configName);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getDockerConfig($configName)
    {
        if (!$this->configuration) {
            return null;
        }
        try {
            return $this->configuration->getDockerConfig($configName);
        } catch (\Exception $e) {
            return null;
        }
    }
}
