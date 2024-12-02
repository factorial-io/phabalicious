<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Validation\ValidationErrorBag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LocalShellProviderTest extends PhabTestCase
{
    /** @var \Phabalicious\ShellProvider\ShellProviderInterface */
    private ShellProviderInterface $shellProvider;

    private ConfigurationService $config;

    /**
     * @var \Phabalicious\Method\TaskContext
     */
    private TaskContext $context;

    public function setUp(): void
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this
            ->config
            ->method("getPasswordManager")
            ->willReturn(new PasswordManager());

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new LocalShellProvider($logger);

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->config);
    }

    public function testGetDefaultConfig(): void
    {
        $config = $this->shellProvider->getDefaultConfig($this->config, new Node([], ''));
        $this->assertArrayHasKey('rootFolder', $config);
    }

    public function testValidateConfig(): void
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig(new Node([], ''), $errors);
        $this->assertEquals(
            ['rootFolder', 'rootFolder', 'shellExecutable'],
            $errors->getKeysWithErrors(),
        );
    }

    public function testValidateConfigRootFolder(): void
    {
        $errors = new ValidationErrorBag();
        $config = new Node(['rootFolder' => '/var/www/', 'shellExecutable' => '/bin/bash'], '');
        $this->shellProvider->validateConfig($config, $errors);
        $this->assertEquals(
            ['rootFolder'],
            $errors->getKeysWithErrors(),
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals('local', $this->shellProvider->getName());
    }

    public function testRun(): void
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => __DIR__
        ], $this->shellProvider, $this->config);

        $test_dir = __DIR__ . '/assets/local-shell-provider';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', RunOptions::CAPTURE_AND_HIDE_OUTPUT);

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

    public function testFailedRun(): void
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/bash',
            'rootFolder' => __DIR__
        ], $this->shellProvider, $this->config);

        $test_dir = __DIR__ . '/assets/local-shell-providerxxx';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->failed());
        $this->assertStringNotContainsString(LocalShellProvider::RESULT_IDENTIFIER, $output);
    }

    public function testHostEnvironment(): void
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/bash',
            'rootFolder' => __DIR__,
            'varC' => 'variable_c',
            'environment' => [
                'VAR_A' => 'variable_a',
                'VAR_B' => 'variable_b',
                'VAR_C' => '%host.varC%',
            ],
        ], $this->shellProvider, $this->config);

        $test_dir = __DIR__ . '/assets/local-shell-provider';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('echo $VAR_A', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->succeeded());
        $this->assertStringNotContainsString(LocalShellProvider::RESULT_IDENTIFIER, $output);
        $this->assertStringContainsString('variable_a', $output);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('echo "XX${VAR_B}XX"', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertStringContainsString('XXvariable_bXX', $output);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('echo "XX${VAR_C}XX"', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertStringContainsString('XXvariable_cXX', $output);
    }

    public function testFileGetContents(): void
    {
        $file = __FILE__;
        $test = $this->shellProvider->getFileContents($file, $this->context);
        $original = file_get_contents($file);
        $this->assertEquals($original, $test);
    }

    public function testFilePutContents(): void
    {
        $content = 'helloworld';
        $result = $this->shellProvider->putFileContents('/tmp/test_put_file_content', $content, $this->context);
        $test = file_get_contents('/tmp/test_put_file_content');
        $this->assertEquals($content, $test);
    }
}
