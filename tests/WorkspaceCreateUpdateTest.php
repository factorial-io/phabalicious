<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\WorkspaceCreateCommand;
use Phabalicious\Command\WorkspaceUpdateCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class WorkspaceCreateUpdateTest extends PhabTestCase
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
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->application->add(new WorkspaceCreateCommand($configuration, $method_factory));
        $this->application->add(new WorkspaceUpdateCommand($configuration, $method_factory));
    }

    private function prepareTarget()
    {

        $target_folder = $this->getcwd() . '/workspace';
        exec(sprintf('rm -rf "%s"', $target_folder));
        if (!is_dir($target_folder)) {
            mkdir($target_folder);
        }
        chdir($target_folder);
        return $target_folder;
    }

    public function testWorkspaceCreate()
    {
        $target_folder = $this->prepareTarget();
        $command = $this->application->find('workspace:create');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--branch' => 'master',
            '--platform' => 'linux',
            '--run-setup' => '',
        ));

        $output = $commandTester->getDisplay();
        $this->assertContains('Happy hacking!', $output);

        $this->checkFileContent($target_folder . '/multibasebox/fabfile.local.yaml', 'runLocally: true');
        return $target_folder;
    }

    public function testWorkspaceUpdate()
    {
        $target_folder = $this->testWorkspaceCreate();
        chdir('multibasebox');
        $command = $this->application->find('workspace:update');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--branch' => 'master',
            '--platform' => 'linux',
            '--run-setup' => '',
        ));

        $output = $commandTester->getDisplay();
        $this->assertContains('Happy hacking!', $output);

        $this->checkFileContent($target_folder . '/multibasebox/fabfile.local.yaml', 'runLocally: true');
    }
}
