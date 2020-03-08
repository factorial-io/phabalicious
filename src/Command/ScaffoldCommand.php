<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Scaffolder\Callbacks\TransformCallback;
use Phabalicious\Utilities\PluginDiscovery;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
        $callback = new TransformCallback();
        $context->mergeAndSet('callbacks', [
            'transform' => [$callback, 'handle']
        ]);

        $context->mergeAndSet('dataOverrides', [
            'variables' => [
                'allowOverride' => true,
                'skipSubfolder' => true,
            ],
            'questions' => [],
            'assets' => [],
        ]);

        return $this->scaffold($url, $root_folder, $context, [], function ($paths) use ($callback) {
            $callback->setTransformers(PluginDiscovery::discover(
                $this->getApplication()->getVersion(),
                $paths,
                'Phabalicious\Scaffolder\Transformers\DataTransformerInterface',
                $this->getConfiguration()->getLogger()
            ));
        });
    }
}
