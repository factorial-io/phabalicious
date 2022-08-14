<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\DeployCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\ArtifactsCustomMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Utilities\TestableLogger;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ArtifactBasedDeploymentTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    protected $logger;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $this->logger = $logger = new TestableLogger();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new ArtifactsCustomMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $configuration->readConfiguration(__DIR__ . '/assets/artifact-based-deployment/fabfile.yaml');

        $this->application->add(new DeployCommand($configuration, $method_factory));
    }

    public function testBrokenConfig()
    {
        $command = $this->application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            '--config'  => 'broken',
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsStringIgnoringCase('[error]', $output);
        $this->assertStringContainsStringIgnoringCase('Missing key artifact.stages', $output);
        $this->assertStringContainsStringIgnoringCase('Missing key artifact.actions', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testMessageActions()
    {
        $command = $this->application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            '--config'  => 'messages',
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsStringIgnoringCase("Hello world error", $output);
        $this->assertStringContainsStringIgnoringCase("Hello world warning", $output);
        $this->assertStringContainsStringIgnoringCase("Hello world success", $output);
        $this->assertStringContainsStringIgnoringCase("Hello world note", $output);
        $this->assertStringContainsStringIgnoringCase("Hello world comment", $output);
        $this->assertStringContainsStringIgnoringCase("", $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testLogActions()
    {
        $command = $this->application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            '--config'  => 'logs',
            '-vv' # everything including info.
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertTrue($this->logger->containsMessage("error", "hello world error"));
        $this->assertTrue($this->logger->containsMessage("warning", "hello world warning"));
        $this->assertTrue($this->logger->containsMessage("notice", "hello world notice"));
        $this->assertTrue($this->logger->containsMessage("info", "hello world info"));
        $this->assertTrue($this->logger->containsMessage("debug", "hello world debug"));
    }
}
