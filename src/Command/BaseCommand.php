<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{

    private $configuration;

    public function __construct(ConfigurationService $configuration, $name = null) {
        $this->configuration = $configuration;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
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
                1
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
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


}