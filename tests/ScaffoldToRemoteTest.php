<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\DeployCommand;
use Phabalicious\Command\ScriptCommand;
use Phabalicious\Command\WebhookCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\SshMethod;
use Phabalicious\Method\WebhookMethod;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldToRemoteTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    public function setup()
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new SshMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $configuration->readConfiguration(__DIR__ . '/assets/test-scaffold-to-remote/fabfile.yaml');

        $this->application->add(new ScriptCommand($configuration, $method_factory));
    }


    /**
     * @group docker
     * @group local-only
     */
    public function testScaffoldToRemote()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--config' => 'clients.factorial.io',
            'script' => 'scaffold-test'
        ));
        $this->assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Scaffolding finished successfully!', $output);
    }

}
