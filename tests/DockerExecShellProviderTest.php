<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.10.18
 * Time: 21:10
 */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\DockerExecShellProvider;
use Phabalicious\ShellProvider\LocalShellProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class DockerExecShellProviderTest extends TestCase
{

    private $config;
    private $shellProvider;
    private $runDockerShell;
    private $backgroundProcess;

    public function setup()
    {
        $this->config = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $this->shellProvider = new DockerExecShellProvider($logger);

        $host_config = new HostConfig([
            'shellExecutable' => 'docker',
            'rootFolder' => '/',
            'docker' => [
                'name' => 'phabalicious_test'
            ]
        ], $this->shellProvider);

        $this->runDockerContainer($logger);
    }

    private function runDockerContainer($logger)
    {
        $runDockerShell = new LocalShellProvider($logger);
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => dirname(__FILE__)
        ], $runDockerShell);

        $result = $runDockerShell->run('docker pull busybox');
        $result = $runDockerShell->run('docker stop phabalicious_test | true');
        $result = $runDockerShell->run('docker rm phabalicious_test | true');


        $this->backgroundProcess = new Process([
            'docker',
            'run',
            '-i',
            '--name',
            'phabalicious_test',
            'busybox',
            '/bin/sh']);
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
    public function testSimpleCommand()
    {

        /** @var CommandResult $result */
        $result = $this->shellProvider->run('whoami', true);
        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals('root', $result->getOutput()[0]);
    }


}
