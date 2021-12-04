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

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion('3.0.0');
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));

        $configuration->readConfiguration(__DIR__ . '/assets/script-tests/fabfile.yaml');

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

        $this->assertStringContainsString('Value A: a', $output);
        $this->assertStringContainsString('Value B: b', $output);
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

        $this->assertStringContainsString('v12', $output);
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

        $this->assertStringContainsString('PHAB_SUB_SHELL=1', $output);
    }

    public function testEncryptDecryptCallback()
    {
        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'crypto',
            'script' => 'testEncryption',
            '--secret' => ['test-secret=very-secure-1234']

        ));

        // Decrypt again.
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            '--config' => 'crypto',
            'script' => 'testDecryption',
            '--secret' => ['test-secret=very-secure-1234']

        ));
        $this->assertEquals(0, $commandTester->getStatusCode());

        $root_dir = __DIR__ . '/assets/script-tests/crypto';
        $files = [
            'test.md',
            'test-jpg.jpg'
        ];
        foreach ($files as $filename) {
            $source = file_get_contents($root_dir . '/source/' . $filename);
            $decrypted = file_get_contents($root_dir . '/decrypted/' . $filename);

            $this->assertEquals($decrypted, $source);
        }
    }
}
