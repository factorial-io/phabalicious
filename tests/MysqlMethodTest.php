<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.10.18
 * Time: 21:10.
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\DatabaseMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class MysqlMethodTest extends PhabTestCase
{
    private ConfigurationService $config;

    private MysqlMethod $method;
    private TaskContext $context;
    private ShellProviderInterface $shell;
    private HostConfig $hostConfig;

    public function setup(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();

        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new MysqlMethod($logger);

        $this->config = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->config, $logger);
        $method_factory->addMethod($this->method);
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setIo($this->getMockBuilder(SymfonyStyle::class)->disableOriginalConstructor()->getMock());

        $host_data = [
            MysqlMethod::SUPPORTS_ZIPPED_BACKUPS => false,
            'backupFolder' => '/tmp',
            'shellProvider' => 'local',
            'configName' => 'test-mysql',
            'needs' => [
                'mysql',
            ],
            'mysqlDumpOptions' => [
                '--column-statistics=0',
            ],
            'executables' => [
                'mysql' => 'mysql',
                'mysqladmin' => 'mysqladmin',
                'mysqldump' => 'mysqldump',
                'grep' => 'grep',
                'cat' => 'cat',
                'gzip' => 'gzip',
                'gunzip' => 'gunzip',
            ],
            'rootFolder' => __DIR__,
            'shellExecutable' => '/bin/sh',
            'database' => [
                'host' => '127.0.0.1',
                'port' => '33060',
                'user' => 'root',
                'pass' => 'admin',
                'name' => 'test-phabalicious',
                'skipCreateDatabase' => false,
                'workingDir' => __DIR__,
            ],
        ];

        $this->config->addHost($host_data);
        $this->hostConfig = $this->config->getHostConfig('test-mysql');
        $this->shell = $this->hostConfig->shell();

        $this->context->setConfigurationService($this->config);

        $this->runDockerContainer($logger);
        $this->method->waitForDatabase($this->hostConfig, $this->context);
    }

    private function runDockerContainer($logger): void
    {
        $runDockerShell = new LocalShellProvider($logger);
        $host_config = new HostConfig([
            'shellExecutable' => '/bin/sh',
            'rootFolder' => __DIR__,
        ], $runDockerShell, $this->config);

        $runDockerShell->run('docker pull mysql', true);
        $runDockerShell->run('docker stop phabalicious_test | true', true);
        $runDockerShell->run('docker rm phabalicious_test | true', true);

        $backgroundProcess = new Process([
            'docker',
            'run',
            '-i',
            '-e',
            'MYSQL_ROOT_PASSWORD=admin',
            '-p',
            '33060:3306',
            '--name',
            'phabalicious_test',
            'mysql',
        ]);
        $input = new InputStream();
        $backgroundProcess->setInput($input);
        $backgroundProcess->setTimeout(0);
        $backgroundProcess->start(function ($type, $buffer) {
            // fwrite(STDOUT, $buffer);
        });
        // Give the container some time to spin up
        sleep(5);
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

    /**
     * @group docker
     */
    public function testInstallDb(): void
    {
        $result = $this->method->install($this->hostConfig, $this->context);
        $this->assertEquals(0, $result->getExitCode());

        $cmd = $this->getExecuteSQLCommand(false, 'SHOW DATABASES');

        $result = $this->shell->run(implode(' ', $cmd));
        $this->assertStringContainsString('test-phabalicious', implode("\n", $result->getOutput()));

        $this->method->dropDatabase($this->hostConfig, $this->context, $this->shell, $this->hostConfig['database']);

        $cmd = $this->getExecuteSQLCommand(true, 'SHOW TABLES');
        $result = $this->shell->run(implode(' ', $cmd));

        $this->assertEquals(0, $result->getExitCode());
        $this->assertCount(0, $result->getOutput());
    }

    /**
     * @dataProvider providerSqlFiles
     *
     * @group docker
     */
    public function testImportExport($filename): void
    {
        $result = $this->method->install($this->hostConfig, $this->context);
        $this->assertEquals(0, $result->getExitCode());

        $result = $this->method->importSqlFromFile($this->hostConfig, $this->context, $this->shell, $filename, true);
        $this->assertEquals(0, $result->getExitCode());

        $cmd = $this->getExecuteSQLCommand(true, 'SHOW TABLES');
        $result = $this->shell->run(implode(' ', $cmd));
        $this->assertEquals(0, $result->getExitCode());
        $this->assertContains('customers', $result->getOutput());
        $this->assertContains('employees', $result->getOutput());
        $this->assertContains('offices', $result->getOutput());

        // Now export the sql again.

        $export_file_name = '/tmp/'.basename($filename);
        $result = $this->method->exportSqlToFile($this->hostConfig, $this->context, $this->shell, $export_file_name);
        $this->assertEquals($export_file_name, $result);

        $this->assertFileExists($export_file_name);
    }

    public function providerSqlFiles(): array
    {
        return [
            [__DIR__.'/../tests/assets/mysqlsampledatabase.sql.gz'],
            [__DIR__.'/../tests/assets/mysqlsampledatabase.sql'],
        ];
    }

    /**
     * @group docker
     */
    public function testDatabaseInstallDrop(): void
    {
        $this->context->set('what', 'install');
        $result = $this->method->database($this->hostConfig, $this->context);
        $this->assertEquals(0, $result->getExitCode());

        $cmd = $this->getExecuteSQLCommand(true, 'SHOW DATABASES');
        $result = $this->shell->run(implode(' ', $cmd));
        $this->assertEquals(0, $result->getExitCode());
        $this->assertContains('test-phabalicious', $result->getOutput());

        $cmd = $this->getExecuteSQLCommand(true, 'USE test-phabalicious;');
        $result = $this->shell->run(implode(' ', $cmd));

        $cmd = $this->getExecuteSQLCommand(true, 'CREATE TABLE test_table(title VARCHAR(100) NOT NULL);');
        $result = $this->shell->run(implode(' ', $cmd));

        $cmd = $this->getExecuteSQLCommand(true, 'SHOW TABLES');
        $result = $this->shell->run(implode(' ', $cmd));

        $this->assertEquals(0, $result->getExitCode());
        $this->assertContains('test_table', $result->getOutput());

        $this->context->set('what', 'drop');
        $result = $this->method->database($this->hostConfig, $this->context);
        $this->assertEquals(0, $result->getExitCode());

        $cmd = $this->getExecuteSQLCommand(true, 'SHOW TABLES');
        $result = $this->shell->run(implode(' ', $cmd));
        $this->assertEquals(0, $result->getExitCode());
        $this->assertCount(0, $result->getOutput());
    }

    /**
     * @dataProvider providerSqlQueries
     *
     * @group docker
     */
    public function testSqlQuery($query, $expected): void
    {
        $this->context->set('what', 'install');
        $result = $this->method->database($this->hostConfig, $this->context);
        $this->assertEquals(0, $result->getExitCode());

        $this->context->set('what', 'query');
        $this->context->set(DatabaseMethod::SQL_QUERY, $query);
        /** @var \Phabalicious\ShellProvider\CommandResult $result */
        $result = $this->method->database($this->hostConfig, $this->context);

        $this->assertEquals(0, $result->getExitCode());
        $this->assertContains($expected, $result->getOutput());
    }

    public function providerSqlQueries(): array
    {
        return [
            [
                'SHOW DATABASES',
                'test-phabalicious',
            ],
            [
                'USE test-phabalicious; CREATE TABLE test_table(title VARCHAR(100) NOT NULL); SHOW TABLES;',
                'test_table',
            ],
        ];
    }
}
