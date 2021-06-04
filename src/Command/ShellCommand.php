<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\ShellOptions;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Tests\Compiler\OptionalParameter;
use Symfony\Component\Process\Process;

class ShellCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setAliases(['ssh']);
        $this
            ->setName('shell')
            ->setDescription('starts an interactive shell')
            ->setHelp('Starts an interactive shell for a given config.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ShellProviderNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->getContext();
        $host_config = $this->getHostConfig();

        // Allow methods to override the used shellProvider:
        $this->getMethods()->runTask('shell', $host_config, $context);
        $shell = $context->getResult('shell', $host_config->shell());

        $output->writeln('<info>Starting shell on `' . $host_config->getConfigName() . '`');

        $options = new ShellOptions();
        $options->setUseTty(true);
        $options->setQuiet(false);

        $process = $this->startInteractiveShell($context->io(), $shell, [], $options);
        return $process->getExitCode();
    }
}
