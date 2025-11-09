<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StartRemoteAccessCommand extends BaseCommand
{
    protected function configure(): void
    {
        $host = gethostname();
        $ip = false;

        if ($host) {
            $ip = gethostbyname($host);
        }
        parent::configure();
        $this->setAliases(['startRemoteAccess']);
        $this
            ->setName('start-remote-access')
            ->setDescription('starts remote access')
            ->setHelp('
Starts remote access to an installation by creating a tunnel or port forwarding.

This command establishes remote access to an installation, typically by creating
an SSH tunnel or similar mechanism. This allows you to access a remote service
(like a web server or database) on your local machine.

Behavior:
- Creates a tunnel or port forwarding based on configuration
- Usually opens a new remote shell session
- Allows accessing the remote installation via local ports
- Type "exit" to close the tunnel when finished
- Automatically detects your machine\'s IP address for the --public-ip default

The exact mechanism depends on your shell provider configuration.

Options:
- --port, -p: Port on the remote host to connect to (default: 80)
- --ip: Host/IP to connect to on the remote side (may not be supported by all shell providers)
- --public-ip: Local IP address to listen on (default: your machine\'s IP or 0.0.0.0)
- --public-port: Local port to listen on (default: 8080)

Examples:
<info>phab --config=myconfig start-remote-access</info>
<info>phab --config=myconfig start-remote-access --port=3306 --public-port=3307</info>
<info>phab --config=myconfig start-remote-access --port=443 --public-port=8443</info>
<info>phab --config=myconfig startRemoteAccess</info>  # Using alias
            ');
        $this->addOption(
            'port',
            'p',
            InputOption::VALUE_OPTIONAL,
            'port to expose on this computer',
            80
        )
        ->addOption(
            'ip',
            null,
            InputOption::VALUE_OPTIONAL,
            'Host/IP to connect to, might not supported by all shell-providers'
        )
        ->addOption(
            'public-ip',
            null,
            InputOption::VALUE_OPTIONAL,
            'public ip on this computer to listen for',
            $ip ?: '0.0.0.0'
        )
        ->addOption(
            'public-port',
            null,
            InputOption::VALUE_OPTIONAL,
            'public port on this computer to listen for',
            8080
        );
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
        $this->getMethods()->runTask('startRemoteAccess', $host_config, $context);

        $ip = $input->getOption('ip') ?: $context->getResult('ip', '127.0.0.1');
        $port = $input->getOption('port');
        $config = $context->getResult('config', $host_config);
        $shell = $context->getShell() ?? $host_config->shell();

        $context->io()->comment(sprintf('Starting remote access to %s:%s', $ip, $port));
        $context->io()->success(sprintf(
            'You should be able to access the remote via %s%s:%s',
            $this->getSchemeFromPort($port),
            $input->getOption('public-ip'),
            $input->getOption('public-port')
        ));

        $context->io()->comment('Usually this will open a new remote shell, type `exit` when you are finished.');

        $shell->startRemoteAccess(
            $ip,
            $port,
            $input->getOption('public-ip'),
            $input->getOption('public-port'),
            $config,
            $context
        );

        return $this->getContext()->getResult('exitCode', 0);
    }

    private function getSchemeFromPort($port): string
    {
        $mapping = [
            '80' => 'http',
            '443' => 'https',
            '22' => 'ssh',
            '3306' => 'mysql',
        ];

        return isset($mapping[$port]) ? $mapping[$port].'://' : '';
    }
}
