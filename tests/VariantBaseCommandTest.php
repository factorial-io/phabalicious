<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27.
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\ScriptCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class VariantBaseCommandTest extends PhabTestCase
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

        $configuration->readConfiguration(__DIR__.'/assets/variants-base-command-tests/fabfile.yaml');

        $this->application->add(new ScriptCommand($configuration, $method_factory));
    }

    public function testNoVariants()
    {
        $this->expectExceptionMessage('Could not find variants for `testMissingVariants` in `blueprints`');
        $this->expectException(\InvalidArgumentException::class);
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--config' => 'testMissingVariants',
            '--variants' => 'all',
        ]);
    }

    public function testUnavailableVariants()
    {
        $this->expectExceptionMessage('Could not find variants `x`, `y`, `z` in `blueprints`');
        $this->expectException(\InvalidArgumentException::class);
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--config' => 'test',
            '--variants' => 'a,b,c,x,y,z',
        ]);
    }

    private function runScript($script_name)
    {
        $path = __DIR__.'/../bin/phab';
        $executable = realpath($path);
        putenv('PHABALICIOUS_EXECUTABLE='.$executable);

        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--config' => 'test',
            '--variants' => 'all',
            '--force' => 1,
            'script' => $script_name,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('--blueprint a', $output);
        $this->assertStringContainsString('--blueprint b', $output);
        $this->assertStringContainsString('--blueprint c', $output);

        $this->assertStringContainsString('XX-test-a-XX', $output);
        $this->assertStringContainsString('XX-test-b-XX', $output);
        $this->assertStringContainsString('XX-test-c-XX', $output);
    }

    /**
     * @group docker
     */
    public function testAllVariants()
    {
        $this->runScript('test');
    }

    /**
     * @group docker
     */
    public function testAllVariantsWithStdErr()
    {
        $this->runScript('testErr');
    }
}
