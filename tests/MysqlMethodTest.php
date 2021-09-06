<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.10.18
 * Time: 21:10
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class MysqlMethodTest extends PhabTestCase
{

    /** @var ConfigurationService */
    private $config;
    private $backgroundProcess;

    private $method;
    private $context;
    private $shell;
    private $hostConfig;

    public function setup()
    {

        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $app = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->getMock();
        $this->method = new MysqlMethod($logger);

        $this->config = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->config, $logger);
        $method_factory->addMethod($this->method);


        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->shell = new LocalShellProvider($logger);
        $this->hostConfig = new HostConfig(
            [
                'executables' => [
                    'mysql' => 'mysql',
                    'mysqladmin' => 'mysqladmin',
                    'mysqldump' => 'mysqldump',
                    'grep' => 'grep',
                ],
                'rootFolder' => $this->getcwd(),
                'shellExecutable' => '/bin/sh',
                'database' => [
                    'host' => '127.0.0.1',
                    'port' => '33060',
                    'user' => 'root',
                    'pass' => 'admin',
                    'name' => 'test-phabalicious',
                    'skipCreateDatabase' => false,
                ],
            ],
            $this->shell,
            $this->config
        );
        $this->context->setConfigurationService($this->config);

        $this->runDockerContainer($logger);
        $this->method->waitForDatabase($this->hostConfig, $this->context);
    }

    private function runDockerContainer($logger)
    {
        $runDockerShell = new LocalShellProvider($logger);
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => dirname(__FILE__)
        ], $runDockerShell, $this->config);

        $result = $runDockerShell->run('docker pull mariadb', true);
        $result = $runDockerShell->run('docker stop phabalicious_test | true', true);
        $result = $runDockerShell->run('docker rm phabalicious_test | true', true);


        $this->backgroundProcess = new Process([
            'docker',
            'run',
            '-i',
            '-e',
            'MARIADB_ROOT_PASSWORD=admin',
            '-p',
            '33060:3306',
            '--name',
            'phabalicious_test',
            'mariadb',
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
    public function testInstallDb()
    {
        $result = $this->method->install($this->hostConfig, $this->context);
        $this->assertEquals(0, $result->getExitCode());

        $cmd = $this->getExecuteSQLCommand(false, "SHOW DATABASES");

        $result = $this->shell->run(implode(' ', $cmd));
        $this->assertStringContainsString('test-phabalicious', implode("\n", $result->getOutput()));

        $this->method->dropDatabase($this->hostConfig, $this->context, $this->shell, $this->hostConfig['database']);

        $cmd = $this->getExecuteSQLCommand(true, "SHOW TABLES");
        $result = $this->shell->run(implode(' ', $cmd));

        $this->assertEquals(0, $result->getExitCode());
        $this->assertEquals(0, count($result->getOutput()));
    }

    public function getExecuteSQLCommand(bool $include_database_arg, string $sql): array
    {
        $cmd = $this->method->getMysqlCommand(
            $this->hostConfig,
            $this->context,
            'mysql',
            $this->hostConfig['database'],
            $include_database_arg
        );
        $cmd[] = '-e';
        $cmd[] = escapeshellarg($sql);
        return $cmd;
    }
}
