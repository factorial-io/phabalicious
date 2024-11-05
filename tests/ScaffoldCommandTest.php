<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\ScaffoldCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldCommandTest extends PhabTestCase
{
    /** @var Application */
    protected Application $application;

    /** @var ConfigurationService  */
    protected ConfigurationService $configuration;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($this->configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));

        $this->application->add(new ScaffoldCommand($this->configuration, $method_factory));
    }

    public function testScaffoldCommand(): void
    {
        $command = $this->application->find('scaffold');
        $command_tester = new CommandTester($command);

        $command_tester->execute([
            'command' => 'scaffold',
            'scaffold-path' => __DIR__ . '/assets/scaffold-tests/simple-scaffold.yml',
        ]);

        $output = $command_tester->getDisplay();
        $this->assertStringContainsStringIgnoringCase("Age: 18", $output);
        $this->assertStringContainsStringIgnoringCase("Location: Hamburg", $output);
    }

    public function testScaffoldCommandWithOptions(): void
    {
        $command = $this->application->find('scaffold');
        $command_tester = new CommandTester($command);

        $command_tester->execute([
            'command' => 'scaffold',
            'scaffold-path' => __DIR__ . '/assets/scaffold-tests/simple-scaffold.yml',
            '--age' => '20',
            '--location' => "Berlin"
        ]);

        $output = $command_tester->getDisplay();
        $this->assertStringContainsStringIgnoringCase("Age: 20", $output);
        $this->assertStringContainsStringIgnoringCase("Location: Berlin", $output);
    }
}
