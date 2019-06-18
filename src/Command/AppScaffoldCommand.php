<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Composer\Semver\Comparator;
use http\Exception\RuntimeException;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class AppScaffoldCommand extends BaseOptionsCommand
{

    protected $twig;

    private $dynamicOptions = [];

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('app:scaffold')
            ->setDescription('Scaffolds an app from a remote scaffold-instruction')
            ->setHelp('Scaffolds an app from a remote scaffold-instruction');

        $this->addArgument(
            'scaffold-url',
            InputArgument::REQUIRED,
            'the url/path to load the scaffold-yaml from'
        );


        $this->addOption(
            'output',
            null,
            InputOption::VALUE_OPTIONAL,
            'the folder where to create the new project',
            false
        );
        $this->addOption(
            'override',
            null,
            InputOption::VALUE_OPTIONAL,
            'Set to true if you want to override existing folders',
            false
        );

        $this->setDefinition(new class($this->getDefinition(), $this->dynamicOptions) extends InputDefinition {
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
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\FailedShellCommandException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getArgument('scaffold-url');
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
            throw new \InvalidArgumentException('Could not read yaml from ' . $url);
        }

        if ($data && isset($data['requires'])) {
            $required_version = $data['requires'];
            $app_version = $this->getApplication()->getVersion();
            if (Comparator::greaterThan($required_version, $app_version)) {
                throw new MismatchedVersionException(
                    sprintf(
                        'Could not read from %s because of version mismatch. %s is required, current app is %s',
                        $url,
                        $required_version,
                        $app_version
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

        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'scaffold');

        $validation->hasKey('scaffold', 'The file needs a scaffold-section.');
        $validation->hasKey('assets', 'The file needs a scaffold-section.');
        $validation->hasKey('questions', 'The file needs a questions-section.');
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        $root_folder = empty($input->getOption('output')) ? getcwd() : $input->getOption('output');
        $tokens = [
            'uuid' => $this->fakeUUID(),
        ];

        $questions = !empty($data['questions']) ? $data['questions'] : [];
        $context = new TaskContext($this, $input, $output);


        $tokens = $this->askQuestions($input, $questions, $context, $tokens);
        if (empty($tokens['name'])) {
            throw new \InvalidArgumentException('Missing `name` in questions, aborting!');
        }
        if (!empty($data['variables'])) {
            $tokens = Utilities::mergeData($data['variables'], $tokens);
        }
        if (empty($tokens['projectFolder'])) {
            $tokens['projectFolder'] = $tokens['name'];
        }

        // Do a first round of replacements.
        $replacements = $this->getReplacements($tokens);
        foreach ($tokens as $ndx => $token) {
            $tokens[$ndx] = strtr($token, $replacements);
        }

        $tokens['projectFolder'] = Utilities::cleanupString($tokens['projectFolder']);
        $tokens['rootFolder'] = realpath($root_folder) . '/' . $tokens['projectFolder'];

        $logger = $this->configuration->getLogger();
        $shell = new LocalShellProvider($logger);
        $script = new ScriptMethod($logger);

        $host_config = new HostConfig([
            'rootFolder' => realpath($input->getOption('output')),
            'shellExecutable' => '/bin/bash'
        ], $shell);

        $context->set('scriptData', $data['scaffold']);
        $context->set('variables', $tokens);
        $context->set('callbacks', [
            'copy_assets' => [$this, 'copyAssets'],
            'log_message' => [$this, 'logMessage']
        ]);
        $context->set('scaffoldData', $data);
        $context->set('tokens', $tokens);
        $context->set('loaderBase', $twig_loader_base);

        // Setup twig
        $loader = new \Twig_Loader_Filesystem($twig_loader_base);
        $this->twig = new \Twig_Environment($loader, array(
        ));


        if (empty($input->getOption('override')) && is_dir($tokens['rootFolder'])) {
            if (!$context->io()->confirm(
                'Destination folder exists! Continue anyways?',
                false
            )) {
                return 1;
            }
        }

        $context->io()->comment('Create destination folder ...');
        $shell->run(sprintf('mkdir -p %s', $tokens['rootFolder']));

        $context->io()->comment('Start scaffolding script ...');
        $script->runScript($host_config, $context);

        /** @var CommandResult $result */
        $result = $context->getResult('commandResult');
        if ($result->failed()) {
            throw new \RuntimeException(sprintf(
                "Scaffolding failed with exit-code %d\n%s",
                $result->getExitCode(),
                implode("\n", $result->getOutput())
            ));
        }

        $context->io()->success('Scaffolding finished successfully!');
        if ($data['successMessage']) {
            $context->io()->block($data['successMessage'], 'Notes', 'fg=white;bg=blue', ' ', true);
        }
        return 0;
    }

    public function logMessage(TaskContextInterface $context, $log_level, $log_message)
    {
        $log_level = strtolower($log_level);
        if ($log_level == 'success') {
            $context->io()->success($log_message);
        } elseif ($log_level == 'warning') {
            $context->io()->warning($log_message);
        } elseif ($log_level == 'error') {
            $context->io()->warning($log_message);
        } else {
            $context->io()->note($log_message);
        }
    }

    /**
     * @param TaskContextInterface $context
     * @param $target_folder
     * @param string $data_key
     * @param bool $limitedForTwigExtension
     */
    public function copyAssets(
        TaskContextInterface $context,
        $target_folder,
        $data_key = 'assets',
        $limitedForTwigExtension = false
    ) {
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }
        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');
        $is_remote = substr($data['base_path'], 0, 4) == 'http';
        $replacements = $this->getReplacements($tokens);

        if (empty($data[$data_key])) {
            throw new \InvalidArgumentException('Scaffold-data does not contain ' . $data_key);
        }

        $context->io()->comment(sprintf('Copying assets `%s`', $data_key));
        $use_progress = count($data[$data_key]) > 3;

        if ($use_progress) {
            $context->io()->progressStart(count($data[$data_key]));
        }

        foreach ($data[$data_key] as $file_name) {
            $tmp_target_file = false;
            if ($is_remote) {
                $tmpl = $this->configuration->readHttpResource($data['base_path'] . '/' . $file_name);
                if ($tmpl === false) {
                    throw new \RuntimeException('Could not read remote asset: '. $data['base_path'] . '/' . $file_name);
                }
                $tmp_target_file = '/tmp/' . $file_name;
                if (!is_dir(dirname($tmp_target_file))) {
                    mkdir(dirname($tmp_target_file), 0777, true);
                }
                file_put_contents('/tmp/' . $file_name, $tmpl);
            }

            if ($limitedForTwigExtension &&
                ('.' . pathinfo($file_name, PATHINFO_EXTENSION) !== $limitedForTwigExtension)
            ) {
                $converted = file_get_contents($context->get('loaderBase') . '/' . $file_name);
            } else {
                $converted = $this->twig->render($file_name, $tokens);
            }

            if ($limitedForTwigExtension) {
                $file_name = str_replace($limitedForTwigExtension, '', $file_name);
            }

            if ($tmp_target_file) {
                unlink($tmp_target_file);
            }

            $file_name = strtr($file_name, $replacements);
            if (strpos($file_name, '/') !== false) {
                $file_name = substr($file_name, strpos($file_name, '/', 1) + 1);
            }

            $target_file_path = $target_folder . '/' . $file_name;
            if (!is_dir(dirname($target_file_path))) {
                mkdir(dirname($target_file_path), 0777, true);
            }

            if ($use_progress) {
                $context->io()->progressAdvance();
            }
            file_put_contents($target_file_path, $converted);
        }
        if ($use_progress) {
            $context->io()->progressFinish();
        }
    }

    private function fakeUUID()
    {
        return bin2hex(openssl_random_pseudo_bytes(4)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(2)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(2)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(2)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(6));
    }

    /**
     * @param InputInterface $input
     * @param array $questions
     * @param TaskContext $context
     * @param array $tokens
     * @return array
     * @throws ValidationFailedException
     */
    protected function askQuestions(InputInterface $input, array $questions, TaskContext $context, array $tokens): array
    {
        foreach ($questions as $key => $question_data) {
            $errors = new ValidationErrorBag();
            $validation = new ValidationService($question_data, $errors, 'questions');
            $validation->hasKey('question', 'Please provide a question');
            if (!empty($question_data['validation'])) {
                $validation->hasKey('validation', 'Please provide a regex for validation');
                $validation->hasKey('error', 'Please provide an error message when a validation fails');
            }
            if ($errors->hasErrors()) {
                throw new ValidationFailedException($errors);
            }

            $option_name = strtolower(preg_replace('%([a-z])([A-Z])%', '\1-\2', $key));
            if (in_array($option_name, $this->dynamicOptions)) {
                $value = $input->getOption($option_name);
            } else {
                $value = $context->io()->ask(
                    $question_data['question'],
                    isset($question_data['default']) ? $question_data['default'] : null
                );
            }

            if (!empty($question_data['validation'])) {
                if (!preg_match($question_data['validation'], $value)) {
                    throw new \InvalidArgumentException($question_data['error'] . ': ' . $value);
                }
            }
            if (!empty($question_data['transform'])) {
                $transform = strtolower($question_data['transform']);
                $mapping = [
                    'lowercase' => 'strtolower',
                    'uppercase' => 'strtoupper',
                ];
                if (isset($mapping[$transform])) {
                    $value = call_user_func($mapping[$transform], $value);
                }
            }
            $tokens[$key] = trim($value);
        }
        return $tokens;
    }

    /**
     * @param $tokens
     * @return array
     */
    protected function getReplacements($tokens): array
    {
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['%' . $key . '%'] = $value;
        }
        return $replacements;
    }
}
