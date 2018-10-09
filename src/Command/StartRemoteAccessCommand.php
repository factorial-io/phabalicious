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
        parent::configure();
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
            'public-ip',
            'pi',
            InputOption::VALUE_OPTIONAL,
            'public ip on this computer to listen for',
            '0.0.0.0'
        )
        ->addOption(
            'public-port',
            'pp',
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $context = new TaskContext($this, $input, $output);
        $host_config = $this->getHostConfig();
        $this->getMethods()->runTask('startRemoteAccess', $host_config, $context);

        $ip = $context->getResult('ip', '127.0.0.1');
        $port = $input->getOption('port');
        $config = $context->getResult('config', $host_config);

        $output->writeln('<info>Starting remote access to ' . $ip . ':' . $port .'</info>');

        $host_config->shell()->startRemoteAccess(
            $ip,
            $port,
            $input->getOption('public-ip'),
            $input->getOption('public-port'),
            $config,
            $context
        );
    }

}