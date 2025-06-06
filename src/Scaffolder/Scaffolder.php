<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Scaffolder;

use Composer\Semver\Comparator;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Exception\YamlParseException;
use Phabalicious\Method\Callbacks\WebHookCallback;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CopyAssetsCallback;
use Phabalicious\Scaffolder\Callbacks\DecryptAssetsCallback;
use Phabalicious\Scaffolder\TwigExtensions\EncryptExtension;
use Phabalicious\Scaffolder\TwigExtensions\GetSecretExtension;
use Phabalicious\Scaffolder\TwigExtensions\Md5Extension;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\DryRunShellProvider;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\QuestionFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;

class Scaffolder
{
    protected $twig;

    protected $questionFactory;

    protected $configuration;

    /** @var \Phabalicious\ShellProvider\ShellProviderInterface */
    protected $shell;

    /** @var TaskContextInterface */
    protected $context;

    public function __construct(ConfigurationService $configuration)
    {
        $this->questionFactory = new QuestionFactory();
        $this->configuration = $configuration;
    }

    /**
     * Scaffold sth from an file/url.
     *
     * @param string $url
     *
     * @return CommandResult
     *
     * @throws FabfileNotReadableException
     * @throws FailedShellCommandException
     * @throws MismatchedVersionException
     * @throws MissingScriptCallbackImplementation
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException|YamlParseException
     */
    public function scaffold(
        $url,
        $root_folder,
        TaskContextInterface $in_context,
        array $tokens = [],
        ?Options $options = null,
    ) {
        if (!$options) {
            $options = new Options();
        }

        $is_remote = false;
        $root_path = $options->getRootPath();
        if (!$data = $options->getScaffoldDefinition()) {
            $orig_url = $url;
            $root_path = dirname($url);
            try {
                if (!Utilities::isHttpUrl($url)) {
                    $is_phar = Utilities::isPharUrl($url);
                    $fullpath = $is_phar ? $url : realpath($url);
                    if (empty($fullpath)) {
                        throw new \RuntimeException(sprintf('Could not find file at `%s`!', $url));
                    }
                    $url = $fullpath;
                    $data = new Node(Yaml::parseFile($url), $url);
                } else {
                    if ($data = $this->configuration->readHttpResource($url)) {
                        $data = new Node(Yaml::parse($data), $url);
                        $is_remote = true;
                    }
                }
            } catch (ParseException $e) {
                throw new YamlParseException(sprintf('Could not parse `%s` (%s)! Working dir: %s', $orig_url, $url, getcwd()), 0, $e);
            }
            if (!$data) {
                throw new \InvalidArgumentException('Could not read yaml from '.$url);
            }
        }

        $io = $options->isQuiet() ? new SymfonyStyle($in_context->getInput(), new NullOutput()) : $in_context->io();
        $this->context = $context = clone $in_context;
        $context->setIo($io);

        // Allow implementation to override parts of the data.
        $overrides = new Node($context->get('dataOverrides', []), 'overrides');
        $data = $overrides->merge($data);

        if ($data->has('requires')) {
            $required_version = $data['requires'];
            if (Comparator::greaterThan($required_version, $options->getCompabilityVersion())) {
                throw new MismatchedVersionException(sprintf('Could not read from %s because of version mismatch. %s is required, current app is %s', $url, $required_version, $options->getCompabilityVersion()));
            }
        }
        if (!empty($data['secrets'])) {
            $context->getConfigurationService()->setSetting('secrets', $data['secrets']);
            unset($data['secrets']);
        }

        $data['base_path'] = $root_path;
        if (!empty($data['baseUrl']) && empty($options->getBaseUrl())) {
            $options->setBaseUrl($data['baseUrl']);
        }

        if ($options->getBaseUrl()) {
            $this->configuration->setInheritanceBaseUrl($options->getBaseUrl());
        }

        $this->configuration->resolveRelativeInheritanceRefs($data, $options->getBaseUrl());

        $data = $this->configuration->resolveInheritance($data, new Node([], 'scaffolder defaults'), $root_path);
        $this->configuration->reportDeprecations($root_path);

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

        $logger = $this->configuration->getLogger();
        $script = new ScriptMethod($logger);

        $shell = null;
        if ($options->isDryRun()) {
            $shell = new DryRunShellProvider($logger);
            $shell->setOutput($context->getOutput());
        }
        if (!$shell) {
            $shell = $options->getShell();
        }
        if (!$shell) {
            $shell = new LocalShellProvider($logger);
        }
        if ($shell->getHostConfig()) {
            $host_config = $shell->getHostConfig();
        } else {
            $host_config = new HostConfig([
                'rootFolder' => realpath($root_folder),
                'shellExecutable' => '/bin/bash',
            ], $shell, $this->configuration);
        }

        $this->shell = $shell;

        if (empty($data['questions']['name'])) {
            $tokens['name'] = $tokens['name'] ?? basename($root_folder);
        }
        if ($options->getSkipSubfolder() && $options->getAllowOverride()) {
            $root_folder = dirname($root_folder);
        }

        $tokens['uuid'] = Utilities::generateUUID();
        $tokens['scaffoldTimestamp'] = (new \DateTime())->format("Ymd-His\Z");

        if (isset($tokens['name']) && $options->useCacheTokens()) {
            $tokens = Utilities::mergeData($this->readTokens($root_folder, $tokens['name']), $tokens);
        }

        if ($data->has('about')) {
            $io->block($data['about'], null, 'fg=yellow', '  ', true);
        }

        $questions = !empty($data['questions']) ? $data['questions'] : [];
        $tokens = $this->askQuestions($questions, $context, $tokens, $options);
        if (!empty($data['variables'])) {
            $tokens = Utilities::mergeData($data['variables'], $tokens);
        }
        if (empty($tokens['name'])) {
            throw new \InvalidArgumentException('Missing `name` in questions, aborting!');
        }

        if (empty($tokens['projectFolder'])) {
            $tokens['projectFolder'] = $tokens['name'];
        }

        $tokens = Utilities::mergeData(
            Utilities::getGlobalReplacements($context->getConfigurationService()),
            $tokens
        );
        $variables = $tokens;
        foreach ($options->getVariables() as $key => $value) {
            $variables[$key] = $value;
        }
        // Do a first round of replacements.
        $replacements = Utilities::expandVariables($variables);
        $tokens = Utilities::expandStrings($tokens, $replacements);
        $tokens = $context->getConfigurationService()->getPasswordManager()->resolveSecrets($tokens);

        $tokens['projectFolder'] = Utilities::cleanupString($tokens['projectFolder']);
        $real_root_folder = $shell->realPath($root_folder);
        if (false === $real_root_folder) {
            throw new \RuntimeException('Could not resolve root-folder '.$root_folder);
        }
        $tokens['rootFolder'] = $real_root_folder.'/'.$tokens['projectFolder'];

        $context->set(ScriptMethod::SCRIPT_DATA, $data['scaffold']);
        if (isset($data['computedValues'])) {
            $context->set(ScriptMethod::SCRIPT_COMPUTED_VALUES, $data['computedValues']);
        }

        $context->set('variables', $tokens);
        $context->set('options', $options);

        $context->set('scaffoldData', $data->asArray());
        $context->set('tokens', $tokens);
        $context->set('rootPath', $root_path);

        $twig_root_path = '/tmp/phab-twig-'.bin2hex(random_bytes(8));
        $context->set('twigRootPath', $twig_root_path);
        mkdir($twig_root_path, 0777, true);

        // Setup twig
        $loader = new FilesystemLoader($twig_root_path);
        $this->twig = new Environment($loader, []);
        $this->twig->addExtension(new StringExtension());
        $this->twig->addExtension(new Md5Extension());
        $this->twig->addExtension(new GetSecretExtension($this->configuration->getPasswordManager()));
        $this->twig->addExtension(new EncryptExtension($this->configuration->getPasswordManager()));

        $options
            ->addCallback(new CopyAssetsCallback($this->configuration, $this->twig))
            ->addCallback(new DecryptAssetsCallback($this->configuration, $this->twig))
            ->addCallback(new WebHookCallback())
            ->addDefaultCallbacks();

        $context->set('callbacks', $options->getCallbacks());
        $context->getConfigurationService()->setSetting('webhooks', $data['webhooks'] ?? []);

        if (is_dir($tokens['rootFolder']) && !$options->getAllowOverride()) {
            if (!$io->confirm(
                'Destination folder exists! Continue anyways?',
                false
            )) {
                throw new \RuntimeException(sprintf('Destination `%s` folder exists already', $tokens['rootFolder']));
            }
        }
        if ($context->getOutput()->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $io->note('Available tokens:'.PHP_EOL.print_r($tokens, true));
        }

        if (empty($tokens['skipCreateDestinationFolder'])) {
            $io->comment(sprintf('Create destination folder `%s`...', $tokens['rootFolder']));
            $shell->run(sprintf('mkdir -p %s', $tokens['rootFolder']));
        }

        $io->comment('Start scaffolding script ...');
        $script->runScript($host_config, $context);

        exec(sprintf('rm -rf "%s"', $twig_root_path));

        /** @var CommandResult $result */
        $result = $context->getResult('commandResult', new CommandResult(0, []));
        if ($result && $result->failed()) {
            throw new \RuntimeException(sprintf("Scaffolding failed with exit-code %d\n%s", $result->getExitCode(), implode("\n", $result->getOutput())));
        }

        if ($options->useCacheTokens()) {
            $this->writeTokens($tokens['rootFolder'], $tokens);
        }
        $success_message = $data['successMessage'] ?? 'Scaffolding finished successfully!';
        if (!is_array($success_message)) {
            $success_message = [$success_message];
        }

        $success_variables = Utilities::mergeData(Utilities::buildVariablesFrom($host_config, $context), $variables);
        $replacements = Utilities::expandVariables($success_variables);
        $success_message = Utilities::expandStrings($success_message, $replacements);
        $io->success($success_message);

        if ($options->isDryRun() && ($shell instanceof DryRunShellProvider)) {
            $result = new CommandResult(0, $shell->getCapturedCommands());
        }
        $in_context->mergeResults($context);

        return $result;
    }

    protected function askQuestions(
        array $questions,
        TaskContextInterface $context,
        array $tokens,
        Options $options,
    ): array {
        return $this->questionFactory->askMultiple(
            $questions,
            $context,
            $tokens,
            function ($key, &$value) use ($options) {
                $option_name = strtolower(preg_replace('%([a-z])([A-Z])%', '\1-\2', $key));
                $dynamic_option = $options->getDynamicOption($option_name);
                if (false !== $dynamic_option && null !== $dynamic_option) {
                    $value = $dynamic_option;
                }
            }
        );
    }

    /**
     * Get local scaffold file.
     */
    public function getLocalScaffoldFile($name): string
    {
        $rootFolder = \Phar::running()
            ? \Phar::running().'/config/scaffold'
            : realpath(__DIR__.'/../../config/scaffold/');

        return $rootFolder.'/'.$name;
    }

    protected function writeTokens(string $root_folder, $tokens)
    {
        $this->shell->putFileContents($root_folder.'/.phab-scaffold-tokens', Yaml::dump($tokens), $this->context);
    }

    /**
     * @param string $name
     *
     * @return array|mixed
     */
    protected function readTokens(string $root_folder, $name): mixed
    {
        $full_path = "$root_folder/$name/.phab-scaffold-tokens";
        if (!$this->shell->exists($full_path)) {
            return [];
        }
        $content = $this->shell->getFileContents($full_path, $this->context);
        $tokens = Yaml::parse($content);

        return is_array($tokens) ? $tokens : [];
    }
}
