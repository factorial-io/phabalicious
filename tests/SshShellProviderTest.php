<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Validation\ValidationErrorBag;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class SshShellProviderTest extends PhabTestCase
{
    /** @var \Phabalicious\ShellProvider\ShellProviderInterface */
    private $shellProvider;

    private $config;

    private $logger;

    /**
     * @var \Phabalicious\Method\TaskContext
     */
    private $context;

    public function setUp()
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->config->method("getPasswordManager")->will($this->returnValue(new PasswordManager()));

        $logger = $this->logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new SshShellProvider($logger);

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->config);
    }

    public function testGetDefaultConfig()
    {
        $this->assertArrayHasKey('port', $this->shellProvider->getDefaultConfig($this->config, []));
        $this->assertArrayHasKey('rootFolder', $this->shellProvider->getDefaultConfig($this->config, []));
    }

    public function testValidateConfig()
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig([], $errors);
        $this->assertEquals(
            ['host', 'port', 'rootFolder', 'rootFolder', 'shellExecutable','user'],
            $errors->getKeysWithErrors(),
            '',
            0.0,
            10,
            true
        );
    }

    public function testValidateConfigRootFolder()
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig([
            'rootFolder' => '/var/www/',
            'shellExecutable' => '/bin/bash',
            'host' => 'localhost',
            'user' => 'foobar',
            'port' => '1234',
        ], $errors);
        $this->assertEquals(
            ['rootFolder'],
            $errors->getKeysWithErrors(),
            '',
            0.0,
            10,
            true
        );
    }

    public function testGetName()
    {
        $this->assertEquals('ssh', $this->shellProvider->getName());
    }


    /**
     * @group docker
     */
    public function testRun()
    {
        $this->getDockerizedSshShell($this->logger, $this->config);

        $test_dir = '/';

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', true);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->succeeded());
        $this->assertContains('.dockerenv', $output);
        $this->assertContains('config', $output);
        $this->assertNotContains(LocalShellProvider::RESULT_IDENTIFIER, $output);

        $result = $this->shellProvider
            ->run('pwd');
        $this->assertTrue(count($result->getOutput()) >= 1);
        $this->assertEquals($test_dir, trim($result->getOutput()[0]));
    }


    /**
     * @group docker
     */
    public function testFilePutContents()
    {
        $shell = $this->getDockerizedSshShell($this->logger, $this->config);

        $content = 'helloworld';
        $result = $shell->putFileContents('/tmp/test_put_file_content', $content, $this->context);
        $test = $shell->getFileContents('/tmp/test_put_file_content', $this->context);
        $this->assertEquals($content, $test);
    }
}
