<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Scaffolder;

use Composer\Semver\Comparator;
use InvalidArgumentException;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\AlterJsonFileCallback;
use Phabalicious\Scaffolder\Callbacks\AssertFileCallback;
use Phabalicious\Scaffolder\Callbacks\ConfirmCallback;
use Phabalicious\Scaffolder\Callbacks\CopyAssetsCallback;
use Phabalicious\Scaffolder\Callbacks\LogMessageCallback;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\QuestionFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Phar;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Scaffolder
{

    protected $twig;

    protected $questionFactory;

    protected $configuration;

    public function __construct(ConfigurationService $configuration)
    {
        $this->questionFactory = new QuestionFactory();
        $this->configuration = $configuration;
    }

    /**
     * Scaffold sth from an file/url.
     *
     * @param $url
     * @param $root_folder
     * @param TaskContextInterface $context
     * @param array $tokens
     * @param Options|null $options
     * @return CommandResult
     * @throws FabfileNotReadableException
     * @throws FailedShellCommandException
     * @throws MismatchedVersionException
     * @throws MissingScriptCallbackImplementation
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    public function scaffold(
        $url,
        $root_folder,
        TaskContextInterface $context,
        array $tokens = [],
        Options $options = null
    ) {
        if (!$options) {
            $options = new Options();
        }

        $is_remote = false;
        if (substr($url, 0, 4) !== 'http') {
            $data = Yaml::parseFile($url);
            $twig_loader_base = dirname($url);
        } else {
            $data = $this->configuration->readHttpResource($url);
            $data = Yaml::parse($data);
            $twig_loader_base = '/tmp';
            $is_remote = true;
        }
        if (!$data) {
            throw new InvalidArgumentException('Could not read yaml from ' . $url);
        }

        // Allow implementation to override parts of the data.
        $data = Utilities::mergeData($context->get('dataOverrides', []), $data);

        if ($data && isset($data['requires'])) {
            $required_version = $data['requires'];
            if (Comparator::greaterThan($required_version, $options->getCompabilityVersion())) {
                throw new MismatchedVersionException(
                    sprintf(
                        'Could not read from %s because of version mismatch. %s is required, current app is %s',
                        $url,
                        $required_version,
                        $options->getCompabilityVersion()
                    )
                );
            }
        }

        $data['base_path'] = dirname($url);

        if (!empty($data['inheritsFrom'])) {
            if (!is_array($data['inheritsFrom'])) {
                $data['inheritsFrom'] = [$data['inheritsFrom']];
            }
            if ($is_remote) {
                foreach ($data['inheritsFrom'] as $item) {
                    if (substr($item, 0, 4) !== 'http') {
                        $data['inheritsFrom'] = $data['base_path'] . '/' . $item;
                    }
                }
            }
        }

        $data = $this->configuration->resolveInheritance($data, [], dirname($url));
        if (!empty($data['plugins']) && $options->getPluginRegistrationCallback()) {
            $options->getPluginRegistrationCallback()($data['plugins']);
        }

        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'scaffold');

        $validation->hasKey('scaffold', 'The file needs a scaffold-section.');
        $validation->hasKey('assets', 'The file needs a assets-section.');
        $validation->hasKey('questions', 'The file needs a questions-section.');
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        if ($options->getSkipSubfolder() && $options->getAllowOverride()) {
            $tokens['name'] = $tokens['name'] ?? basename($root_folder);
            $root_folder = dirname($root_folder);
        }

        $tokens['uuid'] = Utilities::generateUUID();
        $tokens['scaffoldTimestamp'] = (new \DateTime())->format("Ymd-His\Z");

        if (isset($tokens['name']) && $options->useCacheTokens()) {
            $tokens = Utilities::mergeData($this->readTokens($root_folder, $tokens['name']), $tokens);
        }

        $questions = !empty($data['questions']) ? $data['questions'] : [];
        $tokens = $this->askQuestions($context->getInput(), $questions, $context, $tokens, $options);
        if (!empty($data['variables'])) {
            $tokens = Utilities::mergeData($data['variables'], $tokens);
        }
        if (empty($tokens['name'])) {
            throw new InvalidArgumentException('Missing `name` in questions, aborting!');
        }

        if (empty($tokens['projectFolder'])) {
            $tokens['projectFolder'] = $tokens['name'];
        }

        $variables = $tokens;
        foreach ($options->getVariables() as $key => $value) {
            $variables[$key] = $value;
        }
        // Do a first round of replacements.
        $replacements = Utilities::expandVariables($variables);
        $tokens = Utilities::expandStrings($tokens, $replacements);

        $tokens['projectFolder'] = Utilities::cleanupString($tokens['projectFolder']);
        $tokens['rootFolder'] = realpath($root_folder) . '/' . $tokens['projectFolder'];

        $logger = $this->configuration->getLogger();
        $script = new ScriptMethod($logger);

        if ($shell = $options->getShell()) {
            $host_config = $shell->getHostConfig();
        } else {
            $shell = new LocalShellProvider($logger);

            $host_config = new HostConfig([
                'rootFolder' => realpath($root_folder),
                'shellExecutable' => '/bin/bash'
            ], $shell, $this->configuration);
        }

        $context->set(ScriptMethod::SCRIPT_DATA, $data['scaffold']);
        $context->set('variables', $tokens);

        $context->set('scaffoldData', $data);
        $context->set('tokens', $tokens);
        $context->set('loaderBase', $twig_loader_base);

        // Setup twig
        $loader = new FilesystemLoader($twig_loader_base);
        $this->twig = new Environment($loader, array(

        ));

        $options
            ->addCallback(
                CopyAssetsCallback::getName(),
                [new CopyAssetsCallback($this->configuration, $this->twig), 'handle']
            )
            ->addDefaultCallbacks();

        $context->set('callbacks', $options->getCallbacks());

        if (is_dir($tokens['rootFolder']) && !$options->getAllowOverride()) {
            if (!$context->io()->confirm(
                'Destination folder exists! Continue anyways?',
                false
            )) {
                throw new RuntimeException(sprintf("Destination `%s` folder exists already", $tokens['rootFolder']));
            }
        }
        if ($context->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $context->io()->note('Available tokens:' . PHP_EOL . print_r($tokens, true));
        }

        $context->io()->comment('Create destination folder ...');
        $shell->run(sprintf('mkdir -p %s', $tokens['rootFolder']));

        $context->io()->comment('Start scaffolding script ...');
        $script->runScript($host_config, $context);

        /** @var CommandResult $result */
        $result = $context->getResult('commandResult', new CommandResult(0, []));
        if ($result && $result->failed()) {
            throw new RuntimeException(sprintf(
                "Scaffolding failed with exit-code %d\n%s",
                $result->getExitCode(),
                implode("\n", $result->getOutput())
            ));
        }

        if (!empty($data['successMessage'])) {
            $context->io()->block($data['successMessage'], 'Notes', 'fg=white;bg=blue', ' ', true);
        }
        if ($options->useCacheTokens()) {
            $this->writeTokens($tokens['rootFolder'], $tokens);
        }

        $context->io()->success('Scaffolding finished successfully!');
        return $result;
    }

    /**
     * @param InputInterface $input
     * @param array $questions
     * @param TaskContextInterface $context
     * @param array $tokens
     * @param Options $options
     * @return array
     * @throws ValidationFailedException
     */
    protected function askQuestions(
        InputInterface $input,
        array $questions,
        TaskContextInterface $context,
        array $tokens,
        Options $options
    ): array {
        return $this->questionFactory->askMultiple(
            $questions,
            $context,
            $tokens,
            function ($key, &$value) use ($options) {
                $option_name = strtolower(preg_replace('%([a-z])([A-Z])%', '\1-\2', $key));
                $dynamic_option = $options->getDynamicOption($option_name);
                if ($dynamic_option !== false && $dynamic_option !== null) {
                    $value = $dynamic_option;
                }
            }
        );
    }


    /**
     * Get local scaffold file.
     * @param $name
     * @return string
     */
    public function getLocalScaffoldFile($name)
    {
        $rootFolder = Phar::running()
            ? Phar::running() . '/config/scaffold'
            : realpath(__DIR__ . '/../../config/scaffold/');

        return $rootFolder . '/' . $name;
    }

    /**
     * @param $root_folder
     * @param $tokens
     */
    protected function writeTokens($root_folder, $tokens)
    {
        file_put_contents($root_folder . '/.phab-scaffold-tokens', YAML::dump($tokens));
    }

    /**
     * @param $root_folder
     * @param $name
     * @return array|mixed
     */
    protected function readTokens($root_folder, $name)
    {
        $full_path = "$root_folder/$name/.phab-scaffold-tokens";
        if (!file_exists($full_path)) {
            return [];
        }
        $tokens = yaml::parseFile($full_path);
        return is_array($tokens) ? $tokens : [];
    }
}
