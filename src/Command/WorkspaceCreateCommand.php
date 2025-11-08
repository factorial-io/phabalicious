<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Scaffolder\Options;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkspaceCreateCommand extends ScaffoldBaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('workspace:create')
            ->setDescription('Creates a multibasebox workspace')
            ->setHelp('
Creates a new multibasebox workspace on your local machine.

This command scaffolds a complete multibasebox workspace for local development.
A multibasebox workspace is a local development environment for managing multiple projects.

Behavior:
- Uses the mbb/mbb.yml scaffold template to generate workspace files
- Creates the workspace in the current directory by default
- Can optionally override existing folders if --override is set to true
- Interactive prompts will guide you through the setup process

Options:
- --output: Specify a custom folder where to create the workspace (default: current directory)
- --override: Set to true to allow overwriting existing folders

Examples:
<info>phab workspace:create</info>
<info>phab workspace:create --output=/path/to/workspace</info>
<info>phab workspace:create --override=true</info>
            ');

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
     * @throws UnknownReplacementPatternException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $this->createContext($input, $output);
        $url  = $this->scaffolder->getLocalScaffoldFile('mbb/mbb.yml');
        $root_folder = empty($input->getOption('output')) ? getcwd() : $input->getOption('output');

        $options = new Options();
        $options->setUseCacheTokens(false);
        $result = $this->scaffold($url, $root_folder, $context, [], $options);
        return $result->getExitCode();
    }
}
