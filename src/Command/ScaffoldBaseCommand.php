<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use InvalidArgumentException;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\AlterJsonFileCallback;
use Phabalicious\Scaffolder\Callbacks\CopyAssetsCallback;
use Phabalicious\Scaffolder\Callbacks\LogMessageCallback;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\QuestionFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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
            ->setCompabilityVersion($this->getApplication()->getVersion());

        $dynamic_options = [];
        foreach ($this->dynamicOptions as $option_name) {
            $dynamic_options[$option_name] = $context->getInput()->getOption($option_name);
        }
        $options->setDynamicOptions($dynamic_options);

        return $this->scaffolder->scaffold($url, $root_folder, $context, $tokens, $options);
    }
}
