<?php

namespace Phabalicious\Command;

use Phabalicious\ShellProvider\ShellProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShellCommandCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this->setAliases(['sshCommand']);
        $this
            ->setName('shell:command')
            ->setDescription('Prints the command to run an interactive shell')
            ->setHelp('Prints the command to run an interactive shell');
    }

    /**
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

        /** @var ShellProviderInterface $shell */
        $shell = $context->getResult('shell', $host_config->shell());
        $options = $this->getSuitableShellOptions($output);
        $ssh_command = $context->getResult('ssh_command', $shell->getShellCommand([], $options));

        $context->io()->text('$ '.implode(' ', $ssh_command));

        return 0;
    }
}
