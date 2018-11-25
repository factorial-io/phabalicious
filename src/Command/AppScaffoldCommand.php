<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            null,
            InputOption::VALUE_OPTIONAL,
            'the folder where to create the new project',
            getcwd()
        );
        $this->addOption(
            'override',
            null,
            InputOption::VALUE_OPTIONAL,
            'Set to true if you want to override existing folders',
            false
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws ValidationFailedException
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

        $data['base_path'] = dirname($url);

        if ($is_remote && !empty($data['inheritsFrom'])) {
            if (!is_array($data['inheritsFrom'])) {
                $data['inheritsFrom'] = [$data['inheritsFrom']];
            }

            foreach ($data['inheritsFrom'] as $item) {
                if (strpos($item, 0, 4) !== 'http') {
                    $data['inheritsFrom'] =$data['base_path'] . '/' . $item;
                }
            }
        }

        $data = $this->configuration->resolveInheritance($data, [], dirname($url));

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

        if (!empty($data['variables'])) {
            $tokens = Utilities::mergeData($data['variables'], $tokens);
        }




        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'scaffold');

        $validation->hasKey('scaffold', 'The file needs a scaffold-section.');
        $validation->hasKey('assets', 'The file needs a scaffold-section.');
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        $logger = $this->configuration->getLogger();
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
        $loader = new \Twig_Loader_Filesystem($twig_loader_base);
        $this->twig = new \Twig_Environment($loader, array(
        ));

        $shell->run(sprintf('mkdir -p %s', $tokens['rootFolder']));

        if (empty($input->getOption('override')) && is_dir($tokens['rootFolder'])) {
            $question = new ConfirmationQuestion('Target-folder exists! Continue anyways? ', false);
            if (!$helper->ask($input, $output, $question)) {
                return 1;
            }
        }

        $script->runScript($host_config, $context);
        return 0;
    }

    /**
     * @param TaskContextInterface $context
     * @param $target_folder
     * @param string $data_key
     */
    public function copyAssets(TaskContextInterface $context, $target_folder, $data_key = 'assets')
    {
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }
        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');
        $is_remote = substr($data['base_path'], 0, 4) == 'http';
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['%' . $key . '%'] = $value;
        }

        if (empty($data[$data_key])) {
            throw new \InvalidArgumentException('Scaffold-data does not contain ' . $data_key);
        }

        foreach ($data[$data_key] as $file_name) {
            $tmp_target_file = false;
            if ($is_remote) {
                $tmpl = $this->configuration->readHttpResource($data['base_path'] . '/' . $file_name);
                if (empty($tmpl)) {
                    throw new \RuntimeException('Could not read remote asset: '. $data['base_path'] . '/' . $file_name);
                }
                $tmp_target_file = '/tmp/' . $file_name;
                if (!is_dir(dirname($tmp_target_file))) {
                    mkdir(dirname($tmp_target_file), 0777, true);
                }
                file_put_contents('/tmp/' . $file_name, $tmpl);
            }
            $converted = $this->twig->render($file_name, $tokens);
            if ($tmp_target_file) {
                unlink($tmp_target_file);
            }

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
