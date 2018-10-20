<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class BaseOptionsCommand extends Command implements CompletionAwareInterface
{
    protected $configuration;

    protected $methods;


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
                'offline',
                null,
                InputOption::VALUE_OPTIONAL,
                'Do not try to load data from remote hosts, use cached versions if possible',
                false
            );
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName == 'offline') {
            return ['1', '0'];
        }
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
    }

    /**
     * @param InputInterface $input
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function readConfiguration(InputInterface $input)
    {
        $fabfile = !empty($input->getOption('fabfile')) ? $input->getOption('fabfile') : '';
        $this->configuration->setOffline($input->getOption('offline'));
        $this->configuration->readConfiguration(getcwd(), $fabfile);
    }
}