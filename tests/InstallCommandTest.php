<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\InstallCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends PhabTestCase
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
        $method_factory->addMethod(new FilesMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $configuration->readConfiguration(__DIR__.'/assets/install-command/fabfile.yaml');

        $this->application->add(new InstallCommand($configuration, $method_factory));
    }

    public function testSupportsInstallsOnProd()
    {
        $command = $this->application->find('install');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--config' => 'testProd',
            '--force' => true,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Installing new app', $output);
    }

    public function testSupportsInstallsOnStage()
    {
        $command = $this->application->find('install');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--config' => 'testStage',
            '--force' => true,
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Installing new app', $output);
    }
}
