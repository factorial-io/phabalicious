<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkspaceCreateCommand extends ScaffoldBaseCommand
{

    protected $twig;

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('workspace:create')
            ->setDescription('Creates a multibasebox workspace')
            ->setHelp('Scaffolds a multibasebox workspace on your local.');

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
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\FailedShellCommandException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url  = $this->getLocalScaffoldFile('mbb/mbb.yml');
        $root_folder = empty($input->getOption('output')) ? getcwd() : $input->getOption('output');
        $context = new TaskContext($this, $input, $output);

        $this->scaffold($url, $root_folder, $context);
        return 0;
    }
}
