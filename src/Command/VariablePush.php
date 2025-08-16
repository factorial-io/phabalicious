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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class VariablePush extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('variable:push')
            ->setDescription('Pushes a list of variables to a host.')
            ->setHelp('Pushes a list of variables to a host.');
        $this->addArgument('file', InputArgument::REQUIRED, 'yaml file to update, will be created if not existing');
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

        $context->set('action', 'push');
        $filename = $input->getArgument('file');
        $data = Yaml::parseFile($filename);
        $context->set('data', $data);
        $this->getMethods()->runTask('variables', $this->getHostConfig(), $context);

        return 0;
    }
}
