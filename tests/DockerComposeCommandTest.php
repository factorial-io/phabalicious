<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\DockerComposeCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\DockerMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DockerComposeCommandTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion('3.0.0');
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new DockerMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));

        $configuration->readConfiguration(__DIR__.'/assets/docker-compose-command/fabfile.yaml');

        $this->application->add(new DockerComposeCommand($configuration, $method_factory));
    }

    /**
     * @group docker
     */
    public function testDockerComposeRun()
    {
        $command = $this->application->find('docker-compose');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command-arguments' => ['run', 'app'],
            '--config' => 'test',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Hello from Docker!', $output);
    }

    /**
     * @group docker
     */
    public function testDockerComposeWithEnvVars()
    {
        $command = $this->application->find('docker-compose');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command-arguments' => ['-f', 'docker-compose-with-env-vars.yml', 'config'],
            '--config' => 'testEnvVar',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('VHOST: OUR_VHOST_VAR', $output);
    }
}
