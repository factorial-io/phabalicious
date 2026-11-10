<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ScottyShellProvider;
use Phabalicious\ShellProvider\ShellOptions;
use Phabalicious\Utilities\PasswordManagerInterface;
use Psr\Log\AbstractLogger;

/**
 * Runs the scotty shell provider against a fake scottyctl without a tty,
 * e.g. like in CI.
 *
 * @see https://github.com/factorial-io/phabalicious/issues/406
 */
class ScottyShellProviderTest extends PhabTestCase
{
    private ScottyShellProvider $shellProvider;

    private ConfigurationService $config;

    private string $workDir;

    public function setUp(): void
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->onlyMethods(['getPasswordManager', 'getAllSettings'])
            ->disableOriginalConstructor()
            ->getMock();
        $password_manager = $this->getMockBuilder(PasswordManagerInterface::class)->getMock();
        $password_manager->method('resolveSecrets')->willReturnArgument(0);
        $this->config->method('getPasswordManager')->willReturn($password_manager);
        $this->config->method('getAllSettings')->willReturn([]);

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $this->shellProvider = new ScottyShellProvider($logger);
        $this->workDir = $this->getTmpDir('scotty-shell');

        $this->setHostConfig();
    }

    public function tearDown(): void
    {
        $this->shellProvider->terminate();
        parent::tearDown();
    }

    private function setHostConfig(array $scotty = [], array $data = []): void
    {
        $data = array_merge([
            'configName' => 'scotty-test',
            'shellExecutable' => '/bin/sh',
            // The local root folder, must not be used inside the container.
            'rootFolder' => '/this/folder/does/not/exist/in/the/container',
            'executables' => [
                'scottyctl' => __DIR__.'/assets/scotty-tests/fake-scottyctl/scottyctl',
            ],
            'scotty' => array_merge([
                'server' => 'http://localhost:21342',
                'access-token' => 'hello-world',
                'app-name' => 'my-app',
                'shellService' => 'cli',
            ], $scotty),
        ], $data);

        $this->shellProvider->terminate();
        new HostConfig($data, $this->shellProvider, $this->config);
    }

    public function testShellCommandWithoutTtyKeepsStdinOpen(): void
    {
        $command = $this->shellProvider->getShellCommand(['/bin/sh'], new ShellOptions());
        $this->assertContains('--interactive', $command);
        $this->assertNotContains('--shell', $command);
        $this->assertEquals('/bin/sh', $command[array_search('--command', $command) + 1]);
    }

    public function testShellCommandWithTty(): void
    {
        $command = $this->shellProvider->getShellCommand([], (new ShellOptions())->setUseTty(true));
        $this->assertContains('--shell', $command);
        $this->assertNotContains('--interactive', $command);
        $this->assertNotContains('--command', $command);
    }

    public function testRunCommandsWithoutTty(): void
    {
        $result = $this->shellProvider->run('echo hello world', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertTrue($result->succeeded());
        $this->assertEquals(['hello world'], $result->getOutput());

        // Second command runs in the same, persistent shell.
        $this->shellProvider->run('export PHAB_SCOTTY_VAR=persisted', RunOptions::HIDE_OUTPUT);
        $result = $this->shellProvider->run('echo $PHAB_SCOTTY_VAR', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(['persisted'], $result->getOutput());
    }

    public function testExitCodes(): void
    {
        $result = $this->shellProvider->run('(exit 3)', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);
        $this->assertEquals(3, $result->getExitCode());

        $this->assertFalse($this->shellProvider->exists('/this/file/does/not/exist'));
        $this->assertTrue($this->shellProvider->exists('/'));

        $this->expectException(FailedShellCommandException::class);
        $this->shellProvider->run('false');
    }

    public function testLocalRootFolderIsNotUsedInContainer(): void
    {
        $this->assertEquals('', $this->shellProvider->getWorkingDir());
        $result = $this->shellProvider->run('echo ok', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(['ok'], $result->getOutput());
    }

    public function testScottyRootFolderAndCd(): void
    {
        $this->setHostConfig(['rootFolder' => $this->workDir]);
        $this->assertEquals($this->workDir, $this->shellProvider->getWorkingDir());

        $result = $this->shellProvider->run('pwd', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals([realpath($this->workDir)], $result->getOutput());

        mkdir($this->workDir.'/sub');
        $this->shellProvider->cd($this->workDir.'/sub');
        $result = $this->shellProvider->run('pwd', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals([realpath($this->workDir.'/sub')], $result->getOutput());
    }

    public function testEnvironmentIsApplied(): void
    {
        $this->setHostConfig([], ['environment' => ['PHAB_SCOTTY_TEST' => 'it works']]);

        $result = $this->shellProvider->run('echo $PHAB_SCOTTY_TEST', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(['it works'], $result->getOutput());
    }

    public function testFailsFastWithScottyctlWithoutStdinSupport(): void
    {
        $this->setHostConfig([], ['executables' => [
            'scottyctl' => __DIR__.'/assets/scotty-tests/fake-scottyctl/scottyctl-legacy',
        ]]);

        $this->expectException(FailedShellCommandException::class);
        $this->expectExceptionMessageMatches('/--interactive/');
        $this->shellProvider->run('echo hello');
    }
}
