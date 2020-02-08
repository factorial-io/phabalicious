<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\DataTransformerInterface;
use Phabalicious\Utilities\PluginDiscovery;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScaffoldCommand extends ScaffoldBaseCommand
{

    protected $transformers = [];

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('scaffold')
            ->setDescription('Scaffold arbitrary files')
            ->setHelp('Scaffold arbitrary files');

        $this->addArgument(
            'scaffold-path',
            InputArgument::REQUIRED,
            'the path to load the scaffold-yaml from'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
     * @throws FabfileNotReadableException
     * @throws FailedShellCommandException
     * @throws MissingScriptCallbackImplementation
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url  = $input->getArgument('scaffold-path');
        $root_folder = getcwd();
        $context = new TaskContext($this, $input, $output);
        $context->mergeAndSet('callbacks', [
            'transform' => [$this, 'transformCallback']
        ]);

        $this->scaffold($url, $root_folder, $context, [], function ($paths) {
            $this->transformers = PluginDiscovery::discover($paths, 'Phabalicious\Scaffolder\DataTransformerInterface');
        });

        return 0;
    }


    public function transformCallback(TaskContextInterface $context, $transformer_key, $files_key, $target_folder)
    {
        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');

        $files = $data[$files_key] ?? [];
        /** @var DataTransformerInterface $transformer */
        $transformer = $this->transformers[$transformer_key] ?? false;

        if (empty($files)) {
            throw new \InvalidArgumentException('Could not find key in scaffold file ' . $files_key);
        }
        if (!$transformer) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown transformer %s, available transformers %s,',
                $transformer_key,
                implode(', ', array_keys($this->transformers))
            ));
        }

        $context->io()->comment(sprintf('Transforming %s ...', $files_key));

        $result = $transformer->transform($context, $files);

        $context->io()->progressStart(count($result));
        foreach ($result as $file_name => $file_content) {
            $full_path = $tokens['rootFolder'] . '/' . $target_folder . '/' . $file_name;
            $dir = dirname($full_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($full_path, $file_content);
            $context->io()->progressAdvance();
            $context->io()->write(sprintf('File %s created.', $file_name));
        }
        $context->io()->progressFinish();
    }
}
