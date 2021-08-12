<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\ScriptCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ScriptCommandTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    public function setup()
    {
        $this->application = new Application();
        $this->application->setVersion('3.0.0');
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));

        $configuration->readConfiguration($this->getcwd() . '/assets/script-tests/fabfile.yaml');

        $this->application->add(new ScriptCommand($configuration, $method_factory));
    }


    public function testRunScript()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'hostA',
            'script' => 'testDefaults'
        ));

        $output = $commandTester->getDisplay();

        $this->assertContains('Value A: a', $output);
        $this->assertContains('Value B: b', $output);
    }

    /**
     * @group docker
     */
    public function testRunScriptInDockerImageContext()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'hostA',
            'script' => 'testInsideDockerImage'
        ));

        $output = $commandTester->getDisplay();

        $this->assertContains('v12', $output);
    }
    /**
     * @group docker
     */
    public function testRunScriptInDockerImageContext2()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'hostA',
            'script' => 'envInsideDockerImage'
        ));

        $output = $commandTester->getDisplay();

        $this->assertContains('PHAB_SUB_SHELL=1', $output);
    }
}
