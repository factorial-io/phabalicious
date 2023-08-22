<?php

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Tests\Compiler\OptionalParameter;

class StartRemoteAccessCommand extends BaseCommand
{

    protected function configure()
    {
        $host= gethostname();
        $ip = false;

        if ($host) {
            $ip = gethostbyname($host);
        }
        parent::configure();
        $this->setAliases(['startRemoteAccess']);
        $this
            ->setName('start-remote-access')
            ->setDescription('starts remote access')
            ->setHelp('Depending on the configuration phabalicious will create a tunnel ' .
                'so you can access the installation via a local port or sth similar.');
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
            $ip ? $ip : '0.0.0.0'
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

        $context = $this->getContext();
        $host_config = $this->getHostConfig();
        $this->getMethods()->runTask('startRemoteAccess', $host_config, $context);

        $ip = $input->getOption('ip') ? $input->getOption('ip') : $context->getResult('ip', '127.0.0.1');
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

    private function getSchemeFromPort($port)
    {
        $mapping = [
            '80' => 'http',
            '443' => 'https',
            '22' => 'ssh',
            '3306' => 'mysql'
        ];

        return isset($mapping[$port]) ? $mapping[$port] . '://' : '';
    }
}
