<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\AboutCommand;
use Phabalicious\Command\BaseOptionsCommand;
use Phabalicious\Command\ScaffoldCommand;
use Phabalicious\Command\ScriptCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\Utilities\Utilities;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldCommandTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    /** @var ConfigurationService  */
    protected $configuration;

    public function setup()
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

    public function testScaffoldCommand()
    {
        $command = $this->application->find('scaffold');
        $command_tester = new CommandTester($command);

        $command_tester->execute([
            'command' => 'scaffold',
            'scaffold-path' => $this->getcwd() . '/assets/scaffold-tests/simple-scaffold.yml',
        ]);

        $output = $command_tester->getDisplay();
        $this->assertStringContainsStringIgnoringCase("Age: 18", $output);
        $this->assertStringContainsStringIgnoringCase("Location: Hamburg", $output);
    }

    public function testScaffoldCommandWithOptions()
    {
        $command = $this->application->find('scaffold');
        $command_tester = new CommandTester($command);

        $command_tester->execute([
            'command' => 'scaffold',
            'scaffold-path' => $this->getcwd() . '/assets/scaffold-tests/simple-scaffold.yml',
            '--age' => '20',
            '--location' => "Berlin"
        ]);

        $output = $command_tester->getDisplay();
        $this->assertStringContainsStringIgnoringCase("Age: 20", $output);
        $this->assertStringContainsStringIgnoringCase("Location: Berlin", $output);
    }
}
