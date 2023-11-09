<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Scaffolder\Options;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppScaffoldCommand extends ScaffoldBaseCommand
{
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

        $this->setHelp('
Scaffolds a new application from a given url to a scaffold yml file. The scaffold
process will be scaffolded into a new folder named from the project name.

The scaffold-file might contain some questions to set some options. The values
for these questions can be set via environment variables (in upper case snake
case) or by passing them via the command line options (using kebab-case)

For more information about scaffolding new apps, please visit
<href=https://docs.phab.io/app-scaffold.html>the official documentation</> (https://docs.phab.io/app-scaffold.html)


Examples:
<info>phab app:scaffold https://config.factorial.io/scaffold/d9/d9.yml</info>
<info>phab app:scaffold https://config.factorial.io/scaffold/d9/d9.yml --name "test drupal" --short-name td \
  --php-version 8.1</info>
        ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws FabfileNotReadableException
     * @throws FailedShellCommandException
     * @throws MismatchedVersionException
     * @throws MissingScriptCallbackImplementation
     * @throws ValidationFailedException
     * @throws UnknownReplacementPatternException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $this->createContext($input, $output);

        $url = $input->getArgument('scaffold-url');
        $root_folder = empty($input->getOption('output')) ? getcwd() : $input->getOption('output');

        $this->scaffold($url, $root_folder, $context, [], new Options());
        return 0;
    }
}
