<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\AboutCommand;
use Phabalicious\Command\ScriptCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class VariantBaseCommandTest extends TestCase
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
        $method_factory->addMethod(new FilesMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $configuration->readConfiguration(getcwd() . '/assets/variants-base-command-tests/fabfile.yaml');

        $this->application->add(new ScriptCommand($configuration, $method_factory));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Could not find variants for `testMissingVariants` in `blueprints`
     */
    public function testNoVariants()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'testMissingVariants',
            '--variants' => 'all',
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Could not find variants `x`, `y`, `z` in `blueprints`
     */
    public function testUnavailableVariants()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'test',
            '--variants' => 'a,b,c,x,y,z',
        ));
    }

    /**
     * @group docker
     */
    private function runScript($script_name)
    {
        $executable = realpath(getcwd() . '/../bin/phab');
        putenv('PHABALICIOUS_EXECUTABLE=' . $executable);

        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'test',
            '--variants' => 'all',
            '--force' => 1,
            'script' => $script_name
        ));

        $output = $commandTester->getDisplay();
        $this->assertContains('--blueprint a', $output);
        $this->assertContains('--blueprint b', $output);
        $this->assertContains('--blueprint c', $output);

        $this->assertContains('XX-test-a-XX', $output);
        $this->assertContains('XX-test-b-XX', $output);
        $this->assertContains('XX-test-c-XX', $output);
    }

    public function testAllVariants()
    {
        $this->runScript('test');
    }

    public function testAllVariantsWithStdErr()
    {
        $this->runScript('testErr');
    }
}
