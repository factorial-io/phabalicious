<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\DryRunShellProvider;
use Phabalicious\Utilities\PasswordManager;
use Psr\Log\AbstractLogger;

class DryRunShellProviderTest extends PhabTestCase
{
    /** @var \Phabalicious\ShellProvider\ShellProviderInterface */
    private $shellProvider;

    private $config;

    public function setUp(): void
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->config->method("getPasswordManager")->will($this->returnValue(new PasswordManager()));

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new DryRunShellProvider($logger);
    }


    public function testGetName()
    {
        $this->assertEquals('dry-run', $this->shellProvider->getName());
    }

    public function testRun()
    {
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => dirname('/test')
        ], $this->shellProvider, $this->config);

        $test_dir = '/test-directory';

        $this->shellProvider->setHostConfig($host_config);

        $result = $this->shellProvider
            ->cd($test_dir)
            ->run('ls -la', true);

        $this->assertEquals(0, $result->getExitCode());
        $shell_provider = $this->shellProvider;
        $this->assertInstanceOf(DryRunShellProvider::class, $shell_provider);
        $this->assertEquals(['cd /test-directory && ls -la',], $shell_provider->getCapturedCommands());
    }
}
