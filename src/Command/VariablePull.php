<?php

namespace Phabalicious\Command;

use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class VariablePull extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('variable:pull')
            ->setDescription('Pulls a list of variables from a host '.
                'and updates the given yaml file, or create it if it does not exist.')
            ->setHelp('
Pulls a list of variables from a remote host and saves them to a YAML file.

This command retrieves the current values of variables from a remote instance
and stores them in a YAML file. It is useful for backing up variable values
or transferring them between environments using variable:push.

Behavior:
- Reads variable names from the specified YAML file
- Pulls current values for those variables from the remote host
- Merges the pulled values with existing data in the file
- Writes the updated data back to the same file (or a different file if --output is specified)
- Creates the file if it does not exist
- Currently works only for Drupal 7 installations

The YAML file should contain variable names as keys. After pulling, the values
will be populated.

Arguments:
- <file>: Path to the YAML file containing variable names to pull

Options:
- --output, -o: Write output to a different file instead of updating the input file

Examples:
<info>phab --config=myconfig variable:pull variables.yaml</info>
<info>phab --config=production variable:pull vars.yaml --output=backup-vars.yaml</info>
            ');
        $this->addArgument('file', InputArgument::REQUIRED, 'yaml file to update, will be created if not existing');
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL);
    }

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();

        $context->set('action', 'pull');
        $filename = $input->getArgument('file');
        $data = Yaml::parseFile($filename);
        $context->set('data', $data);
        $this->getMethods()->runTask('variables', $this->getHostConfig(), $context);

        $data = Utilities::mergeData($data, $context->getResult('data', []));
        if ($input->getOption('output')) {
            $filename = $input->getOption('output');
        }
        if (file_put_contents($filename, Yaml::dump($data, 4, 2))) {
            $context->io()->success('Variables written to '.$filename);

            return 0;
        }

        $context->io()->error('Could not write to '.$filename);

        return 1;
    }
}
