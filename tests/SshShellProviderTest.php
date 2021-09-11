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

    /**
     * @var \Symfony\Component\Process\Process
     */
    private $backgroundProcess;

    private $logger;

    /**
     * @var false|string
     */
    private $publicKeyFile;

    /**
     * @var false|string
     */
    private $privateKeyFile;

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

        $this->publicKeyFile = realpath(__DIR__ . '/assets/ssh-shell-tests/test_key.pub');
        $this->privateKeyFile = realpath(__DIR__ . '/assets/ssh-shell-tests/test_key');

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

    private function runDockerContainer($logger)
    {
        $runDockerShell = new LocalShellProvider($logger);
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => dirname(__FILE__)
        ], $runDockerShell, $this->config);

        $result = $runDockerShell->run('docker pull ghcr.io/linuxserver/openssh-server', true);
        $result = $runDockerShell->run('docker stop phabalicious_ssh_test | true', true);
        $result = $runDockerShell->run('docker rm phabalicious_ssh_test | true', true);
        $result = $runDockerShell->run(sprintf('chmod 600 %s', $this->privateKeyFile));
        $public_key = trim(file_get_contents($this->publicKeyFile));

        $this->backgroundProcess = new Process([
            'docker',
            'run',
            '-i',
            '-e',
            'PUID=1000',
            '-e',
            'PGID=1000',
            '-e',
            "PUBLIC_KEY=$public_key",
            '-e',
            'USER_NAME=test',
            '-p',
            '22222:2222',
            '--name',
            'phabalicious_ssh_test',
            'ghcr.io/linuxserver/openssh-server',
        ]);
        $input = new InputStream();
        $this->backgroundProcess->setInput($input);
        $this->backgroundProcess->setTimeout(0);
        $this->backgroundProcess->start(function ($type, $buffer) {
            fwrite(STDOUT, $buffer);
        });
        // Give the container some time to spin up
        sleep(5);
    }

    /**
     * @group docker
     */
    public function testRun()
    {
        $this->getDockerizedSshShell();

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
        $this->getDockerizedSshShell();

        $content = 'helloworld';
        $result = $this->shellProvider->putFileContents('/tmp/test_put_file_content', $content, $this->context);
        $test = $this->shellProvider->getFileContents('/tmp/test_put_file_content', $this->context);
        $this->assertEquals($content, $test);
    }

    /**
     * @return string
     */
    public function getDockerizedSshShell()
    {
        $this->runDockerContainer($this->logger);
        $host_config = new HostConfig([
            'configName' => 'ssh-test',
            'shellExecutable' => '/bin/sh',
            'shellProviderExecutable' => '/usr/bin/ssh',
            'disableKnownHosts' => true,
            'rootFolder' => '/',
            'host' => 'localhost',
            'port' => '22222',
            'user' => 'test',
            'shellProviderOptions' => [
                '-i' .
                $this->privateKeyFile
            ],
        ], $this->shellProvider, $this->config);


        $this->shellProvider->setHostConfig($host_config);
    }
}
