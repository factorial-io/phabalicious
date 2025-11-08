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
            ->setHelp('
Starts an interactive shell session on the specified host configuration.

This command opens a live, interactive shell (e.g., SSH, Docker exec, etc.) to the
remote or local environment defined in your host configuration. The type of shell
depends on your shell provider configuration (SSH, Docker, Kubernetes, etc.).

Behavior:
- Opens an interactive shell with TTY support
- Allows methods to override the shell provider
- Displays "Starting shell on `<config-name>`" before connecting
- Shell session runs until you type "exit" or close it
- Exit code from the shell session is returned

This is the actual shell - for just seeing what command would be run,
use "shell:command" instead.

Examples:
<info>phab --config=myconfig shell</info>
<info>phab --config=production ssh</info>  # Using alias
<info>phab shell</info>                     # Uses default config (ddev)
            ');
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
        $host_config = $this->getHostConfig();

        // Allow methods to override the used shellProvider:
        $this->getMethods()->runTask('shell', $host_config, $context);
        $shell = $context->getResult('shell', $host_config->shell());

        $output->writeln('<info>Starting shell on `' . $host_config->getConfigName() . '`');

        $options = new ShellOptions();
        $options->setUseTty(true);
        $options->setQuiet(false);

        $process = $this->startInteractiveShell($context, $shell, [], $options);
        return $process->getExitCode();
    }
}
