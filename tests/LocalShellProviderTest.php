<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Validation\ValidationErrorBag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class LocalShellProviderTest extends TestCase
{
    /** @var \Phabalicious\ShellProvider\ShellProviderInterface */
    private $shellProvider;

    private $config;

    public function setUp()
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new LocalShellProvider($logger);
    }

    public function testGetDefaultConfig()
    {
        $this->assertArrayHasKey('rootFolder', $this->shellProvider->getDefaultConfig($this->config, []));
    }

    public function testValidateConfig()
    {
        $errors = new ValidationErrorBag();
        $this->shellProvider->validateConfig([], $errors);
        $this->assertEquals(['rootFolder', 'shellExecutable'], $errors->getKeysWithErrors(), '', 0.0, 10, true);
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
        ], $this->shellProvider);

        $test_dir = dirname(__FILE__) . '/assets/local-shell-provider';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', true);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->succeeded());
        $this->assertContains('two.txt', $output);
        $this->assertContains('three.txt', $output);
        $this->assertNotContains(LocalShellProvider::RESULT_IDENTIFIER, $output);

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
        ], $this->shellProvider);

        $test_dir = dirname(__FILE__) . '/assets/local-shell-provider';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', true);

        $output = implode(PHP_EOL, $result->getOutput());
        $this->assertTrue($result->failed());
        $this->assertNotContains(LocalShellProvider::RESULT_IDENTIFIER, $output);
    }
}
