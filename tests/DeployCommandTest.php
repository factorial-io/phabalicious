<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\DeployCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DeployCommandTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    protected $callback;

    public function setup()
    {

        $this->callback = new DebugCallback(false);

        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method = new ScriptMethod($logger);
        $method->setDefaultCallbacks([ $this->callback::getName() => $this->callback ]);

        $method_factory->addMethod($method);
        $method_factory->addMethod(new LocalMethod($logger));

        $configuration->readConfiguration(__DIR__ . '/assets/script-tests/fabfile.yaml');

        $this->application->add(new DeployCommand($configuration, $method_factory));
    }

    public function scriptDebugCallback(TaskContextInterface $context, $message)
    {
    }

    public function testScriptOnlyDeployment()
    {
        $this->callback->debugOutput = [];
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
        ], $this->callback->debugOutput);
    }
}
