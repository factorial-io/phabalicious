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
            ->setHelp('
Prints the shell command that would be used to open an interactive shell.

This command outputs the actual shell command (e.g., SSH command) that phabalicious
would execute to connect to the remote host. It does NOT actually open the shell,
but just displays the command for inspection or manual use.

This is useful when you want to:
- See what SSH/shell command phab would run
- Copy the command to use in your own scripts
- Debug connection issues
- Understand the shell provider configuration

For actually opening an interactive shell, use the "shell" command instead.

Behavior:
- Determines the appropriate shell command based on configuration
- Prints the command to stdout
- Does not execute the shell command

Examples:
<info>phab --config=myconfig shell:command</info>
<info>phab --config=production sshCommand</info>  # Using alias
            ');
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
