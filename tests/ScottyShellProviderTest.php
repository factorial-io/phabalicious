<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ScottyShellProvider;
use Phabalicious\ShellProvider\ShellOptions;
use Psr\Log\AbstractLogger;

/**
 * Runs the scotty shell provider against a fake scottyctl which behaves like
 * the real one in non-interactive (`--command`) mode, e.g. in CI without a tty.
 *
 * @see https://github.com/factorial-io/phabalicious/issues/406
 */
class ScottyShellProviderTest extends PhabTestCase
{
    private ScottyShellProvider $shellProvider;

    private string $workDir;

    public function setUp(): void
    {
        $config = $this->getMockBuilder(ConfigurationService::class)
            ->onlyMethods(['getPasswordManager'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->method('getPasswordManager')->willReturn(null);

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $this->shellProvider = new ScottyShellProvider($logger);
        $this->workDir = $this->getTmpDir('scotty-shell');

        new HostConfig($this->getHostConfigData(), $this->shellProvider, $config);
    }

    private function getHostConfigData(array $scotty = []): array
    {
        return [
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
        ];
    }

    public function testRunCommandWithoutTty(): void
    {
        $result = $this->shellProvider->run('echo hello world', RunOptions::CAPTURE_AND_HIDE_OUTPUT);

        $this->assertTrue($result->succeeded());
        $this->assertEquals(['hello world'], $result->getOutput());
    }

    public function testStatusLinesAreNotPartOfTheOutput(): void
    {
        $result = $this->shellProvider->run('printf "a\nb\n"', RunOptions::CAPTURE_AND_HIDE_OUTPUT);

        $this->assertEquals(['a', 'b'], $result->getOutput());
    }

    public function testExitCodeIsPropagated(): void
    {
        $result = $this->shellProvider->run('exit 3', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);
        $this->assertTrue($result->failed());
        $this->assertEquals(3, $result->getExitCode());

        $this->assertFalse($this->shellProvider->exists('/this/file/does/not/exist'));
        $this->assertTrue($this->shellProvider->exists('/'));
    }

    public function testFailingCommandThrows(): void
    {
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
        $config = $this->shellProvider->getHostConfig();
        $this->shellProvider->setHostConfig(new HostConfig(
            $this->getHostConfigData(['rootFolder' => $this->workDir]),
            $this->shellProvider,
            $config->getConfigurationService()
        ));
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
        $config = $this->shellProvider->getHostConfig();
        $data = $this->getHostConfigData();
        $data['environment'] = ['PHAB_SCOTTY_TEST' => 'it works'];
        $this->shellProvider->setHostConfig(new HostConfig(
            $data,
            $this->shellProvider,
            $config->getConfigurationService()
        ));

        $result = $this->shellProvider->run('echo $PHAB_SCOTTY_TEST', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(['it works'], $result->getOutput());
    }

    public function testInteractiveShellCommand(): void
    {
        $options = (new ShellOptions())->setUseTty(true);
        $command = $this->shellProvider->getShellCommand([], $options);
        $this->assertContains('--shell', $command);
        $this->assertNotContains('--command', $command);
    }
}
