<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\SshShellProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class PhabTestCase extends TestCase
{
    protected function getTmpDir($sub_dir = null): string
    {
        $dir = __DIR__.'/tmp';
        if ($sub_dir) {
            $dir .= '/'.$sub_dir;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function tearDown(): void
    {
        shell_exec(sprintf('rm -rf "%s"', $this->getTmpDir()));
        parent::tearDown();
    }

    protected function checkFileContent($filename, $needle): void
    {
        $haystack = file_get_contents($filename);
        $this->assertStringContainsString($needle, $haystack);
    }

    protected function runDockerizedSshServer($logger, ConfigurationService $config): false|string
    {
        $publicKeyFile = realpath(__DIR__.'/assets/ssh-shell-tests/test_key.pub');
        $privateKeyFile = realpath(__DIR__.'/assets/ssh-shell-tests/test_key');

        $runDockerShell = new LocalShellProvider($logger);
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => __DIR__,
        ], $runDockerShell, $config);

        $result = $runDockerShell->run('docker pull ghcr.io/linuxserver/openssh-server', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(0, $result->getExitCode());
        $result = $runDockerShell->run('docker stop phabalicious_ssh_test | true', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(0, $result->getExitCode());
        $result = $runDockerShell->run('docker rm phabalicious_ssh_test | true', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
        $this->assertEquals(0, $result->getExitCode());
        $result = $runDockerShell->run(sprintf('chmod 600 %s', $privateKeyFile));
        $this->assertEquals(0, $result->getExitCode());
        $public_key = trim(file_get_contents($publicKeyFile));

        $backgroundProcess = new Process([
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
        $backgroundProcess->setInput($input);
        $backgroundProcess->setTimeout(0);
        $backgroundProcess->start(function ($type, $buffer) {
            fwrite(STDOUT, $buffer);
        });
        // Give the container some time to spin up
        sleep(10);

        return $privateKeyFile;
    }

    public function getDockerizedSshShell($logger, ConfigurationService $config): SshShellProvider
    {
        $shellProvider = new SshShellProvider($logger);
        $privateKeyFile = $this->runDockerizedSshServer($logger, $config);
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
                '-i'.
                $privateKeyFile,
            ],
        ], $shellProvider, $config);

        $shellProvider->setHostConfig($host_config);

        return $shellProvider;
    }
}
