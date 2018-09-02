<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
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

    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory, $name = null)
    {
        $this->configuration = $configuration;
        $this->methods = $method_factory;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->addOption(
                'fabfile',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Override with a custom fabfile',
                ''
            )
            ->addOption(
                'config',
                null,
                InputOption::VALUE_REQUIRED,
                'Which host-config should be worked on',
                1
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configuration->readConfiguration(getcwd(), $input->getOption('fabfile'));
    }

    /**
     * Get the configuration object.
     *
     * @return ConfigurationService
     */
    protected function getConfiguration()
    {
        return $this->configuration;
    }

    protected function getMethods()
    {
        return $this->methods;
    }

}