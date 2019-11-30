<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\DeployCommand;
use Phabalicious\Command\WebhookCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Method\WebhookMethod;
use Phabalicious\Utilities\Utilities;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class WebhookCommandTest extends PhabTestCase
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
        $method = new WebhookMethod($logger);

        $method_factory->addMethod($method);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $configuration->readConfiguration($this->getcwd() . '/assets/webhook-tests/fabfile.yaml');

        $this->application->add(new WebhookCommand($configuration, $method_factory));
        $this->application->add(new DeployCommand($configuration, $method_factory));
    }

    public function testNonexistingWebhookCommand()
    {
        $this->expectException("RuntimeException");

        $command = $this->application->find('webhook');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'webhook' => 'nonExisitingWebhookName',
            '--config' => 'hostA',
        ));
    }

    public function test404WebhookCommand()
    {
        $this->expectException("GuzzleHttp\Exception\RequestException");

        $command = $this->application->find('webhook');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'webhook' => 'test404',
            '--config' => 'hostA',
        ));
    }

    public function testListWebhookCommand()
    {
        $command = $this->application->find('webhook');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--config' => 'hostA',
        ));
        $this->assertEquals(1, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertContains('testGet', $output);
        $this->assertContains('testDelete', $output);
        $this->assertContains('testPost', $output);
        $this->assertNotContains('defaults', $output);
    }

    public function testWebhookWithArgumentsCommand()
    {
        $command = $this->application->find('webhook');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'webhook' => 'testArguments',
            '--config' => 'hostA',
            '--arguments' => 'q=hello-from-commandline'
        ));
        $this->assertEquals(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();

        $this->assertContains('"args":{"q":"hello-from-commandline"}', $output);
    }

    public function testTaskSpecificWebhooks()
    {
        $command = $this->application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'hostA',
        ));

        $output = $commandTester->getDisplay();

        $this->assertContains('[test2Get]', $output);
        $this->assertContains('[testArguments]', $output);
        $this->assertContains('"args":{"q":"foo"}', $output);
        $this->assertContains('config.factorial.io', $output);
    }
}
