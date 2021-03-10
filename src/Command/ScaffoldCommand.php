<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Scaffolder\Callbacks\TransformCallback;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Utilities\PluginDiscovery;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScaffoldCommand extends ScaffoldBaseCommand
{

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

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_OPTIONAL,
            'if set, then the commands will be printed out, instead of executed.',
            false
        );

        $this->addOption(
            'use-cached-tokens',
            null,
            InputOption::VALUE_OPTIONAL,
            'if set, then the tokens will be written into .phab-scaffold-tokens',
            false
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

        $context = $this->createContext($input, $output);
        $callback = new TransformCallback();
        $context->mergeAndSet('callbacks', [
            'transform' => [$callback, 'handle']
        ]);

        $options = new Options();
        $options
            ->setAllowOverride(true)
            ->setSkipSubfolder(true)
            ->setUseCacheTokens(Utilities::hasBoolOptionSet($input, 'use-cached-tokens'))
            ->setDryRun(Utilities::hasBoolOptionSet($input, 'dry-run'))
            ->addCallback('transform', [new TransformCallback(), 'handle'])
            ->setPluginRegistrationCallback(
                function ($paths) use ($callback) {
                    $callback->setTransformers(PluginDiscovery::discover(
                        $this->getApplication()->getVersion(),
                        $paths,
                        'Phabalicious\Scaffolder\Transformers\DataTransformerInterface',
                        $this->getConfiguration()->getLogger()
                    ));
                }
            );

        $context->mergeAndSet('dataOverrides', [
            'questions' => [],
            'assets' => [],
        ]);

        $result = $this->scaffold(
            $url,
            $root_folder,
            $context,
            [],
            $options
        );

        return $result->getExitCode();
    }
}
