<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\ShellProvider\CommandResult;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

abstract class ScaffoldBaseCommand extends BaseOptionsCommand
{

    protected $dynamicOptions = [];

    protected $scaffolder;

    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory, $name = null)
    {
        parent::__construct($configuration, $method_factory, $name);

        $this->scaffolder = new Scaffolder($configuration);
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->addOption(
                'base-url',
                'b',
                InputOption::VALUE_OPTIONAL,
                'base url to use for relative references using "@"'
            );

        $this->setDefinition(new class($this->getDefinition(), $this->dynamicOptions) extends InputDefinition
        {
            protected $dynamicOptions = [];

            public function __construct(InputDefinition $definition, array &$dynamicOptions)
            {
                parent::__construct();
                $this->setArguments($definition->getArguments());
                $this->setOptions($definition->getOptions());
                $this->dynamicOptions =& $dynamicOptions;
            }

            public function getOption($name)
            {
                if (!parent::hasOption($name)) {
                    $this->addOption(new InputOption($name, null, InputOption::VALUE_OPTIONAL));
                    $this->dynamicOptions[] = $name;
                }
                return parent::getOption($name);
            }

            public function hasOption($name)
            {
                return true;
            }

        });
    }

    /**
     * Scaffold sth from an file/url.
     *
     * @param $url
     * @param $root_folder
     * @param TaskContextInterface $context
     * @param array $tokens
     * @param Options $options
     * @return CommandResult
     * @throws FabfileNotReadableException
     * @throws FailedShellCommandException
     * @throws MismatchedVersionException
     * @throws MissingScriptCallbackImplementation
     * @throws UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    protected function scaffold(
        $url,
        $root_folder,
        TaskContextInterface $context,
        array $tokens,
        Options $options
    ) {
        $options
            ->setAllowOverride(
                empty($context->getInput()->getOption('force'))
                || empty($context->getInput()->getOption('override'))
                || $options->getAllowOverride()
            )
            ->setBaseUrl($context->getInput()->getOption('base-url'))
            ->setCompabilityVersion($this->getApplication()->getVersion());

        $dynamic_options = [];
        foreach ($this->dynamicOptions as $option_name) {
            $dynamic_options[$option_name] = $context->getInput()->getOption($option_name);
        }
        $options->setDynamicOptions($dynamic_options);

        return $this->scaffolder->scaffold($url, $root_folder, $context, $tokens, $options);
    }
}
