<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Tests\Compiler\OptionalParameter;
use Symfony\Component\Process\Process;

class ShellCommandCommand extends BaseCommand
{

    protected function configure()
    {
        parent::configure();
        $this->setAliases(['sshCommand']);
        $this
            ->setName('shell:command')
            ->setDescription('Prints the command to run an interactive shell')
            ->setHelp('Prints the command to run an interactive shell');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = $this->createContext($input, $output);
        $host_config = $this->getHostConfig();

        // Allow methods to override the used shellProvider:
        $this->getMethods()->runTask('shell', $host_config, $context);

        /** @var ShellProviderInterface $shell */
        $shell = $context->getResult('shell', $host_config->shell());
        $ssh_command = $context->getResult('ssh_command', $shell->getShellCommand([]));

        $context->io()->text('$ ' . implode(' ', $ssh_command));

        return 0;
    }
}
