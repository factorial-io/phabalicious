<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\DeployCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DeployCommandTest extends TestCase
{
    /** @var Application */
    protected $application;

    protected $debugOutput = [];

    public function setup()
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method = new ScriptMethod($logger);
        $method->setDefaultCallbacks([
            'debug' => [ $this, 'scriptDebugCallback']
        ]);

        $method_factory->addMethod($method);
        $method_factory->addMethod(new LocalMethod($logger));

        $configuration->readConfiguration(getcwd() . '/assets/script-tests/fabfile.yaml');

        $this->application->add(new DeployCommand($configuration, $method_factory));
    }

    public function scriptDebugCallback(TaskContextInterface $context, $message)
    {
        $this->debugOutput[] = $message;
    }

    public function testScriptOnlyDeployment()
    {
        $this->debugOutput = [];
        $command = $this->application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'hostA',
        ));

        $this->assertEquals([
            'deployPrepare on dev',
            'deployPrepare on hostA',
            'deploy on dev',
            'deploy on hostA',
            'deployFinished on dev',
            'deployFinished on hostA'
        ], $this->debugOutput);
    }
}
