<?php

/** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Validation\ValidationErrorBag;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SshShellProviderTest extends PhabTestCase
{
    private SshShellProvider $shellProvider;

    private $config;

    private $logger;

    private TaskContext $context;

    public function setUp(): void
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->config->method('getPasswordManager')->willReturn(new PasswordManager());

        $logger = $this->logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new SshShellProvider($logger);

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->config);
    }

    public function testGetDefaultConfig(): void
    {
        $this->assertArrayHasKey('port', $this->shellProvider->getDefaultConfig($this->config, new Node([], '')));
        $this->assertArrayHasKey('rootFolder', $this->shellProvider->getDefaultConfig($this->config, new Node([], '')));
    }

    public function testValidateConfig(): void
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig(new Node([], ''), $errors);
        $this->assertEqualsCanonicalizing(
            ['host', 'port', 'rootFolder', 'rootFolder', 'shellExecutable', 'user'],
            $errors->getKeysWithErrors()
        );
    }

    public function testValidateConfigRootFolder(): void
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig(new Node([
            'rootFolder' => '/var/www/',
            'shellExecutable' => '/bin/bash',
            'host' => 'localhost',
            'user' => 'foobar',
            'port' => '1234',
        ], ''), $errors);
        $this->assertEquals(
            ['rootFolder'],
            $errors->getKeysWithErrors(),
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals('ssh', $this->shellProvider->getName());
    }

    /**
     * @group docker
     */
    public function testRun(): void
    {
        $shell_provider = $this->getDockerizedSshShell($this->logger, $this->config);

        $test_dir = '/';

        $result = $shell_provider
            ->cd($test_dir)
            ->run('ls -la', true);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->succeeded());
        $this->assertStringContainsString('.dockerenv', $output);
        $this->assertStringContainsString('config', $output);
        $this->assertStringNotContainsString(LocalShellProvider::RESULT_IDENTIFIER, $output);

        $result = $shell_provider
            ->run('pwd');
        $this->assertTrue(count($result->getOutput()) >= 1);
        $this->assertEquals($test_dir, trim($result->getOutput()[0]));
    }

    /**
     * @group docker
     */
    public function testFilePutContents(): void
    {
        $shell = $this->getDockerizedSshShell($this->logger, $this->config);

        $content = 'helloworld';
        $result = $shell->putFileContents('/tmp/test_put_file_content', $content, $this->context);
        $test = $shell->getFileContents('/tmp/test_put_file_content', $this->context);
        $this->assertEquals($content, $test);
    }
}
