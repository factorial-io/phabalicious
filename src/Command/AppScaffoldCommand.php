<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class AppScaffoldCommand extends BaseOptionsCommand
{

    protected $twig;

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
            'name',
            null,
            InputOption::VALUE_OPTIONAL,
            'the name of the app to create'
        );
        $this->addOption(
            'short-name',
            's',
            InputOption::VALUE_OPTIONAL,
            'the short name of the app to create'
        );

        $this->addOption(
            'output',
            '',
            InputOption::VALUE_OPTIONAL,
            'the folder where to create the new project',
            getcwd()
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getArgument('scaffold-url');
        if (substr($url, 0, 4) !== 'http') {
            $data = Yaml::parseFile($url);
        } else {
            $data = $this->configuration->readHttpResource($url);
        }
        if (!$data) {
            throw new \InvalidArgumentException('Could not read yaml from ' . $url);
        }

        $data['base_path'] = dirname($url);

        $helper = $this->getHelper('question');

        if (!$name = $input->getOption('name')) {
            $question = new Question('Please provide the name of the new project: ');
            $name = $helper->ask($input, $output, $question);
        }

        if (!$short_name = $input->getOption('short-name')) {
            $question = new Question('Please provide the short name of the new project (1-3 letters): ');
            $short_name = $helper->ask($input, $output, $question);
        }
        if (strlen($short_name) > 3 || !ctype_alnum($short_name)) {
            throw new \InvalidArgumentException(
                'Shortname contains non-alphanumeric letter or is longer than 3 letters'
            );
        }
        $tokens = [
            'name' => trim($name),
            'shortName' => trim(strtolower($short_name)),
            'projectFolder' => Utilities::cleanupString($name),
            'rootFolder' => $input->getOption('output') . '/' . Utilities::cleanupString($name),
            'uuid' => $this->fakeUUID(),
        ];


        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'scaffold');

        $validation->hasKey('scaffold', 'The file needs a scaffold-section.');
        $validation->hasKey('assets', 'The file needs a scaffold-section.');
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }


        // @todo: Get from somewhere else,
        $logger = new ConsoleLogger($output);

        $shell = new LocalShellProvider($logger);
        $script = new ScriptMethod($logger);

        $host_config = new HostConfig([
            'rootFolder' => $input->getOption('output'),
            'shellExecutable' => '/bin/bash'
        ], $shell);
        $context = new TaskContext($this, $input, $output);

        $context->set('scriptData', $data['scaffold']);
        $context->set('variables', $tokens);
        $context->set('callbacks', [
            'copy_assets' => [$this, 'copyAssets'],
        ]);
        $context->set('scaffoldData', $data);
        $context->set('tokens', $tokens);

        // Setup twig
        $loader = new \Twig_Loader_Filesystem($data['base_path']);
        $this->twig = new \Twig_Environment($loader, array(
        ));

        $shell->run(sprintf('mkdir -p %s', $tokens['rootFolder']));

        if (is_dir($tokens['rootFolder'])) {
            $question = new ConfirmationQuestion('Target-folder exists? Continue anyways? ', false);
            if (!$helper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $script->runScript($host_config, $context);
        return 0;
    }

    public function copyAssets(TaskContextInterface $context, $target_folder, $data_key = 'assets')
    {
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }

        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['%' . $key . '%'] = $value;
        }

        if (empty($data[$data_key])) {
            throw new \InvalidArgumentException('Scaffold-data does not contain ' . $data_key);
        }

        foreach ($data[$data_key] as $file_name) {
            $converted = $this->twig->render($file_name, $tokens);

            $target_file_name = $target_folder. '/' . strtr(basename($file_name), $replacements);
            $context->getOutput()->writeln(sprintf('Creating %s ...', $target_file_name));
            file_put_contents($target_file_name, $converted);
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

}
