<?php

/** @noinspection PhpRedundantCatchClauseInspection */

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
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('workspace:update')
            ->setDescription('Updates a multibasebox workspace')
            ->setHelp('Updates a multibasebox workspace on your local.');
    }

    /**
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

        $url = $this->scaffolder->getLocalScaffoldFile('mbb/mbb-update.yml');
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
        if (file_exists($start_folder.'/setup-docker.sh')) {
            return $start_folder;
        }
        --$max_level;
        $start_folder = dirname($start_folder);
        if (0 == $max_level || DIRECTORY_SEPARATOR == $start_folder) {
            return false;
        }

        return $this->findRootFolder($start_folder, $max_level - 1);
    }
}
