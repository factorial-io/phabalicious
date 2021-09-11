<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\AppCreateCommand;
use Phabalicious\Command\ResetCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\DockerMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Utilities\AppDefaultStages;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AppCreateCommandTest extends PhabTestCase
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
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new DockerMethod($logger));

        $configuration->readConfiguration(__DIR__ . '/assets/app-create-tests/fabfile.yaml');

        $this->application->add(new AppCreateCommand($configuration, $method_factory));
        $this->application->add(new ResetCommand($configuration, $method_factory));

        AppDefaultStages::setStagesNeedingRunningApp([]);
    }

    public function testAppCreateWithoutPrepare()
    {
        $target_folder = $this->getTmpDir();
        if (!is_dir($target_folder)) {
            mkdir($target_folder);
        }

        $command = $this->application->find('app:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            '--config'  => 'test',
            '--force' => 1,
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertNotContains('Could not validate', $output);
        $this->assertContains('XX Spin up XX', $output);
        $this->assertContains('XX Install XX', $output);
    }

    public function testAppCreateWithPrepare()
    {
        $target_folder = $this->getTmpDir();
        if (!is_dir($target_folder)) {
            mkdir($target_folder);
        }

        $command = $this->application->find('app:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            '--config'  => 'testWithPrepare',
            '--force' => 1,
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertNotContains('Could not validate', $output);
        $this->assertContains('XX Spin up XX', $output);
        $this->assertContains('XX prepareDestination XX', $output);
        $this->assertContains('XX Install XX', $output);
    }
}
