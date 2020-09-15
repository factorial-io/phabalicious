<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseOptionsCommand extends Command implements CompletionAwareInterface
{
    /**
     * @var ConfigurationService
     */
    protected $configuration;

    /**
     * @var MethodFactory
     */
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
                'skip-cache',
                null,
                InputOption::VALUE_OPTIONAL,
                'Skip local cached files.',
                false
            )
            ->addOption(
                'offline',
                null,
                InputOption::VALUE_OPTIONAL,
                'Do not try to load data from remote hosts, use cached versions if possible',
                false
            )
            ->addOption(
                'arguments',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Pass optional arguments',
                []
            );
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName == 'offline') {
            return ['1', '0'];
        }
        if ($optionName == 'skip-cache') {
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
        $offline = $input->getOption('offline');
        $skip_cache = $input->getOption('skip-cache');
        $fabfile = !empty($input->getOption('fabfile')) ? $input->getOption('fabfile') : '';

        // Options w/o value have the value null, if not set, the default value, which is false.
        $this->configuration
            ->setOffline(is_null($offline) || !empty($offline))
            ->setSkipCache(is_null($skip_cache) || !empty($skip_cache));
        $this->configuration->readConfiguration(getcwd(), $fabfile);
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
     * Check if all required params are available.
     *
     * @param InputInterface $input
     */
    protected function checkAllRequiredOptionsAreNotEmpty(InputInterface $input)
    {
        $errors = [];
        $options = $this->getDefinition()->getOptions();

        /** @var InputOption $option */
        foreach ($options as $option) {
            $name = $option->getName();
            /** @var mixed $value */
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
    protected function parseScriptArguments(array $defaults, $arguments_string)
    {
        if (empty($arguments_string)) {
            return ['arguments' => $defaults];
        }

        $named_args = Utilities::parseArguments($arguments_string);

        return [
            'arguments' => Utilities::mergeData($defaults, $named_args),
        ];
    }

    /**
     * Create a TaskContextInterface and prefill it.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $default_arguments
     * @return TaskContext
     */
    protected function createContext(InputInterface $input, OutputInterface $output, $default_arguments = [])
    {
        $context = new TaskContext($this, $input, $output);
        $arguments = $this->parseScriptArguments($default_arguments, $input->getOption('arguments'));
        $context->set('variables', $arguments);
        $context->set('deployArguments', $arguments);
        $context->set(
            ScriptMethod::SCRIPT_COMMAND_LINE_DEFAULTS,
            $this->parseScriptArguments([], $input->getOption('arguments'))['arguments'] ?? []
        );

        return $context;
    }
}
