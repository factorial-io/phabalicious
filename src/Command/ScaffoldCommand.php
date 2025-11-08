<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ValidationFailedException;
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
            ->setHelp('
Scaffolds files from a template using a scaffold YAML definition.

Scaffolding is phabalicious\'s system for generating project files from templates.
This command reads a scaffold YAML file that defines questions, assets, and
transformations, then generates files in the current directory based on your answers.

Scaffold files can be:
- Local files (e.g., /path/to/scaffold.yml)
- URLs (e.g., https://example.com/scaffold.yml)
- Built-in scaffolds from phabalicious

Behavior:
- Reads the scaffold definition from <scaffold-path>
- Prompts for any questions defined in the scaffold
- Generates files in the current working directory
- Applies transformations and replacements as defined in the scaffold
- Allows overriding existing files
- Can discover and use custom transformer plugins

Options:
- --dry-run: Print the commands that would be executed instead of running them
- --use-cached-tokens: Save user answers to .phab-scaffold-tokens for reuse

Arguments:
- <scaffold-path>: Path or URL to the scaffold YAML file

Examples:
<info>phab scaffold /path/to/my-scaffold.yml</info>
<info>phab scaffold https://example.com/project-template.yml</info>
<info>phab scaffold template.yml --dry-run</info>
<info>phab scaffold template.yml --use-cached-tokens</info>
            ');

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
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
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
            ->setPluginRegistrationCallback(
                function ($paths) use ($callback) {
                    $callback->setTransformers(PluginDiscovery::discover(
                        $this->getApplication()->getVersion(),
                        $paths,
                        'Phabalicious\Scaffolder\Transformers\DataTransformerInterface',
                        'Phabalicious\\Scaffolder\\Transformers\\',
                        $this->getConfiguration()->getLogger()
                    ));
                }
            )
            ->addCallback(new TransformCallback());


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
