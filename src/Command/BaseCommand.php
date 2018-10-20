<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Exception\MissingHostConfigException;
use Psr\Log\NullLogger;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends BaseOptionsCommand
{
    private $hostConfig;

    private $dockerConfig;


    protected function configure()
    {
        $default_conf = getenv('PHABALICIOUS_DEFAULT_CONFIG');
        if (empty($default_conf)) {
            $default_conf = null;
        }
        $this
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_REQUIRED,
                'Which host-config should be worked on',
                $default_conf
            )
            ->addOption(
                'blueprint',
                null,
                InputOption::VALUE_OPTIONAL,
                'Which blueprint to use',
                null
            );

        parent::configure();
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName == 'config') {
            $config = new ConfigurationService($this->getApplication(), new NullLogger());
            $config->setOffline(true);
            try {
                $config->readConfiguration(getcwd());
            } catch (\Exception $e) {
                return [];
            }
            return array_keys($config->getAllHostConfigs());
        }
        return parent::completeOptionValues($optionName, $context);
    }

    /**
     * {@inheritdoc}
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkAllRequiredOptionsAreNotEmpty($input);

        $config_name = '' . $input->getOption('config');

        try {
            $this->readConfiguration($input);

            if ($input->hasOption('blueprint') && $blueprint = $input->getOption('blueprint')) {
                $this->hostConfig = $this->getConfiguration()->getHostConfigFromBlueprint(
                    $config_name,
                    $blueprint
                );
            } else {
                $this->hostConfig = $this->getConfiguration()->getHostConfig($config_name);
            }

            if (!empty($this->hostConfig['docker']['configuration'])) {
                $docker_config_name = $this->hostConfig['docker']['configuration'];
                $this->dockerConfig = $this->getConfiguration()->getDockerConfig($docker_config_name);
            }

            if ($this->hostConfig->shell()) {
                $this->hostConfig->shell()->setOutput($output);
            }
        } catch (MissingHostConfigException $e) {
            $output->writeln('<error>Could not find host-config named `' . $config_name . '`</error>');
            return 1;
        } catch (ValidationFailedException $e) {
            $output->writeln('<error>Could not validate config `' . $config_name . '`</error>');
            foreach ($e->getValidationErrors() as $error_msg) {
                $output->writeln('<error>' . $error_msg . '</error>');
            }
            return 1;
        }

        return 0;
    }

    private function checkAllRequiredOptionsAreNotEmpty(InputInterface $input)
    {
        $errors = [];
        $options = $this->getDefinition()->getOptions();

        /** @var InputOption $option */
        foreach ($options as $option) {
            $name = $option->getName();
            /** @var InputOption $value */
            $value = $input->getOption($name);

            if ($option->isValueRequired() &&
                ($value === null || $value === '' || ($option->isArray() && empty($value)))
            ) {
                $errors[] = sprintf('The required option --%s is not set or is empty', $name);
            }
        }

        if (count($errors)) {
            throw new \InvalidArgumentException(implode("\n\n", $errors));
        }
    }

    /**
     * Get the configuration object.
     *
     * @return ConfigurationService
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    protected function getMethods()
    {
        return $this->methods;
    }

    /**
     * Get host config.
     *
     * @return HostConfig
     */
    protected function getHostConfig()
    {
        return $this->hostConfig;
    }

    protected function getDockerConfig()
    {
        return $this->dockerConfig;
    }

}