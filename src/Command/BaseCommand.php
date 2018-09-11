<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MethodInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{

    private $configuration;

    private $methods;

    private $hostConfig;

    private $dockerConfig;

    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory, $name = null)
    {
        $this->configuration = $configuration;
        $this->methods = $method_factory;
        parent::__construct($name);
    }

    protected function configure()
    {
        $default_conf = getenv('PHABALICIOUS_DEFAULT_CONFIG');
        if (empty($default_conf)) {
            $default_conf = null;
        }
        $this
            ->addOption(
                'fabfile',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Override with a custom fabfile'
            )
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
    }

    /**
     * {@inheritdoc}
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $fabfile = !empty($input->getOption('fabfile')) ? $input->getOption('fabfile') : '';
            $this->configuration->readConfiguration(getcwd(), $fabfile);

            $config_name = '' . $input->getOption('config');
            $this->hostConfig = $this->getConfiguration()->getHostConfig($config_name);

            if (!empty($this->hostConfig['docker']['configuration'])) {
                $docker_config_name = $this->hostConfig['docker']['configuration'];
                $this->dockerConfig = $this->getConfiguration()->getDockerConfig($docker_config_name);
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

    protected function getHostConfig()
    {
        return $this->hostConfig;
    }

    protected function getDockerConfig()
    {
        return $this->dockerConfig;
    }

}