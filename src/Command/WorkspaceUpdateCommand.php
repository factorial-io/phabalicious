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
use Symfony\Component\Console\Output\OutputInterface;

class WorkspaceUpdateCommand extends ScaffoldBaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('workspace:update')
            ->setDescription('Updates a multibasebox workspace')
            ->setHelp('
Updates a multibasebox workspace on your local machine.

This command updates the configuration and setup scripts for a multibasebox workspace.
A multibasebox workspace is a local development environment for managing multiple projects.

Behavior:
- Searches for the workspace root folder by looking for setup-docker.sh
  (traverses up to 10 directory levels from current directory)
- Uses the mbb/mbb-update.yml scaffold template to update workspace files
- Updates workspace configuration based on the workspace name

Examples:
<info>phab workspace:update</info>
            ');
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

        $url  = $this->scaffolder->getLocalScaffoldFile('mbb/mbb-update.yml');
        $root_folder = $this->findRootFolder(getcwd());
        if (!$root_folder) {
            throw new \InvalidArgumentException('Could not find multibasebox root folder!');
        }

        $name = basename($root_folder);
        $root_folder = dirname($root_folder);
        $options = new Options();
        $options->setUseCacheTokens(false);
        $this->scaffold($url, $root_folder, $context, ['name' => $name], $options);
        return 0;
    }

    private function findRootFolder($start_folder, $max_level = 10)
    {
        if (file_exists($start_folder . '/setup-docker.sh')) {
            return $start_folder;
        }
        $max_level--;
        $start_folder = dirname($start_folder);
        if ($max_level == 0 || $start_folder == DIRECTORY_SEPARATOR) {
            return false;
        }
        return $this->findRootFolder($start_folder, $max_level - 1);
    }
}
