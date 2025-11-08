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
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class VariablePush extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this
            ->setName('variable:push')
            ->setDescription('Pushes a list of variables to a host.')
            ->setHelp('
Pushes a list of variables from a YAML file to a remote host.

This command reads variable names and values from a YAML file and sets them
on the remote instance. It is useful for restoring variables that were previously
saved using variable:pull.

Behavior:
- Reads variables from the specified YAML file
- Pushes all variables to the remote host configuration
- Sets each variable to the value specified in the YAML file
- Currently works only for Drupal 7 installations

The YAML file format should contain variable names as keys and their values.

Arguments:
- <file>: Path to the YAML file containing variables to push

Examples:
<info>phab --config=myconfig variable:push variables.yaml</info>
<info>phab --config=production variable:push /path/to/vars.yaml</info>
            ');
        $this->addArgument('file', InputArgument::REQUIRED, 'yaml file to update, will be created if not existing');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();

        $context->set('action', 'push');
        $filename = $input->getArgument('file');
        $data = Yaml::parseFile($filename);
        $context->set('data', $data);
        $this->getMethods()->runTask('variables', $this->getHostConfig(), $context);

        return 0;
    }
}
