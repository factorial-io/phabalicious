<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Validation\ValidationErrorBag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LocalShellProviderTest extends PhabTestCase
{
    /** @var \Phabalicious\ShellProvider\ShellProviderInterface */
    private $shellProvider;

    private $config;

    /**
     * @var \Phabalicious\Method\TaskContext
     */
    private $context;

    public function setUp(): void
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->config->method("getPasswordManager")->will($this->returnValue(new PasswordManager()));

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new LocalShellProvider($logger);

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->config);
    }

    public function testGetDefaultConfig()
    {
        $this->assertArrayHasKey('rootFolder', $this->shellProvider->getDefaultConfig($this->config, []));
    }

    public function testValidateConfig()
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig([], $errors);
        $this->assertEquals(
            ['rootFolder', 'rootFolder', 'shellExecutable'],
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
        $this->shellProvider->validateConfig(['rootFolder' => '/var/www/', 'shellExecutable' => '/bin/bash'], $errors);
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
        $this->assertEquals('local', $this->shellProvider->getName());
    }

    public function testRun()
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => dirname(__FILE__)
        ], $this->shellProvider, $this->config);

        $test_dir = dirname(__FILE__) . '/assets/local-shell-provider';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', true);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->succeeded());
        $this->assertStringContainsString('two.txt', $output);
        $this->assertStringContainsString('three.txt', $output);
        $this->assertStringNotContainsString(LocalShellProvider::RESULT_IDENTIFIER, $output);

        $result = $this->shellProvider
            ->run('pwd');
        $this->assertTrue(count($result->getOutput()) >= 1);
        $this->assertEquals($test_dir, trim($result->getOutput()[0]));
    }

    public function testFailedRun()
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/bash',
            'rootFolder' => dirname(__FILE__)
        ], $this->shellProvider, $this->config);

        $test_dir = dirname(__FILE__) . '/assets/local-shell-providerxxx';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', true, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->failed());
        $this->assertStringNotContainsString(LocalShellProvider::RESULT_IDENTIFIER, $output);
    }

    public function testHostEnvironment()
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/bash',
            'rootFolder' => dirname(__FILE__),
            'varC' => 'variable_c',
            'environment' => [
                'VAR_A' => 'variable_a',
                'VAR_B' => 'variable_b',
                'VAR_C' => '%host.varC%',
            ],
        ], $this->shellProvider, $this->config);

        $test_dir = dirname(__FILE__) . '/assets/local-shell-provider';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('echo $VAR_A', true, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->succeeded());
        $this->assertStringNotContainsString(LocalShellProvider::RESULT_IDENTIFIER, $output);
        $this->assertStringContainsString('variable_a', $output);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('echo "XX${VAR_B}XX"', true, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertStringContainsString('XXvariable_bXX', $output);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('echo "XX${VAR_C}XX"', true, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertStringContainsString('XXvariable_cXX', $output);
    }

    public function testFileGetContents()
    {
        $file = __FILE__;
        $test = $this->shellProvider->getFileContents($file, $this->context);
        $original = file_get_contents($file);
        $this->assertEquals($original, $test);
    }

    public function testFilePutContents()
    {
        $content = 'helloworld';
        $result = $this->shellProvider->putFileContents('/tmp/test_put_file_content', $content, $this->context);
        $test = file_get_contents('/tmp/test_put_file_content');
        $this->assertEquals($content, $test);
    }
}
