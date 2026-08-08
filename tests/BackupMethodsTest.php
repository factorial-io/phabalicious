<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\ResticMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\DryRunShellProvider;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BackupMethodsTest extends PhabTestCase
{
    private ConfigurationService $configurationService;
    private MethodFactory $methodFactory;
    private TaskContext $context;
    private FilesMethod $filesMethod;
    private ResticMethod $resticMethod;
    private DryRunShellProvider $dryRunShell;

    public function setUp(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->configurationService = new ConfigurationService($app, $logger);

        $this->methodFactory = new MethodFactory($this->configurationService, $logger);
        $this->methodFactory->addMethod(new LocalMethod($logger));
        $this->methodFactory->addMethod(new ScriptMethod($logger));
        $this->methodFactory->addMethod(new MysqlMethod($logger));
        $this->filesMethod = new FilesMethod($logger);
        $this->resticMethod = new ResticMethod($logger);
        $this->methodFactory->addMethod($this->filesMethod);
        $this->methodFactory->addMethod($this->resticMethod);

        $this->configurationService->readConfiguration(__DIR__.'/assets/backup-tests/fabfile.yaml');

        // Create dry-run shell for testing without executing commands
        $this->dryRunShell = new DryRunShellProvider($logger);

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);
        $this->context->setIo($this->getMockBuilder(SymfonyStyle::class)->disableOriginalConstructor()->getMock());
    }

    private function getHostConfigWithDryRunShell(string $hostName): HostConfig
    {
        $hostConfig = $this->configurationService->getHostConfig($hostName);
        // Set dry-run shell on context so methods use it instead of real shell
        $this->dryRunShell->setHostConfig($hostConfig);
        $this->context->set('shell', $this->dryRunShell);

        return $hostConfig;
    }

    /**
     * Test that FilesMethod::collectBackupMethods adds 'files' when using files strategy.
     */
    public function testFilesMethodCollectsFilesAsBackupMethod(): void
    {
        $hostConfig = $this->configurationService->getHostConfig('hostWithFiles');

        $this->methodFactory->runTask('collectBackupMethods', $hostConfig, $this->context);

        $backupMethods = $this->context->getResult('backupMethods', []);
        $this->assertContains('files', $backupMethods);
    }

    /**
     * Test that ResticMethod::collectBackupMethods should add 'files' (not 'restic')
     * since 'what' describes what to backup, not how.
     */
    public function testResticMethodShouldCollectFilesAsBackupMethod(): void
    {
        $hostConfig = $this->configurationService->getHostConfig('hostWithRestic');

        $this->methodFactory->runTask('collectBackupMethods', $hostConfig, $this->context);

        $backupMethods = $this->context->getResult('backupMethods', []);
        // 'what' should be 'files', not 'restic' - restic is the 'how'
        $this->assertContains('files', $backupMethods, 'ResticMethod should add "files" to backupMethods, not "restic"');
        $this->assertNotContains('restic', $backupMethods, '"restic" is how we backup, not what we backup');
    }

    /**
     * Test that ResticMethod::backup checks for 'files' in what, not 'restic'.
     */
    public function testResticBackupChecksForFilesInWhat(): void
    {
        $hostConfig = $this->getHostConfigWithDryRunShell('hostWithRestic');

        // Verify precondition: fileBackupStrategy is 'restic'
        $this->assertEquals('restic', $hostConfig->get('fileBackupStrategy'));

        // Simulate 'phab backup files'
        $this->context->set('what', ['files']);
        $this->context->setResult('basename', ['test', '2024-01-01--12-00-00']);

        $this->resticMethod->backup($hostConfig, $this->context);

        $files = $this->context->getResult('files', []);
        $this->assertNotEmpty($files, 'ResticMethod::backup should add file entries when what=["files"]');
        $this->assertContains('restic', array_column($files, 'type'));
    }

    /**
     * Test that FilesMethod::backup does NOT run when restic is the strategy.
     */
    public function testFilesMethodBackupSkipsWhenResticIsStrategy(): void
    {
        $hostConfig = $this->getHostConfigWithDryRunShell('hostWithRestic');

        // Verify that fileBackupStrategy is 'restic'
        $this->assertEquals('restic', $hostConfig->get('fileBackupStrategy'));

        $this->context->set('what', ['files']);
        $this->context->setResult('basename', ['test', '2024-01-01--12-00-00']);

        $this->filesMethod->backup($hostConfig, $this->context);

        $files = $this->context->getResult('files', []);
        // Should be empty because FilesMethod checks fileBackupStrategy
        $this->assertEmpty($files, 'FilesMethod::backup should skip when fileBackupStrategy is "restic"');
    }

    /**
     * Test that no duplicates appear in collectBackupMethods when both files and restic are in needs.
     * Only the active strategy should advertise 'files'.
     */
    public function testNoDuplicateBackupMethodsWhenBothMethodsPresent(): void
    {
        $hostConfig = $this->configurationService->getHostConfig('hostWithBoth');

        $this->methodFactory->runTask('collectBackupMethods', $hostConfig, $this->context);

        $backupMethods = $this->context->getResult('backupMethods', []);

        // Only the active strategy (restic, since it sets fileBackupStrategy) should add 'files'
        $filesCount = count(array_filter($backupMethods, fn ($m) => 'files' === $m));
        $this->assertEquals(1, $filesCount, 'Only one "files" entry should be present in backupMethods');
    }

    /**
     * Test full backup with db and files using files strategy.
     * Simulates: phab backup db files.
     */
    public function testBackupDbAndFilesWithFilesStrategy(): void
    {
        $hostConfig = $this->getHostConfigWithDryRunShell('hostWithDbAndFiles');

        $this->context->set('what', ['db', 'files']);
        $this->context->setResult('basename', ['test', '2024-01-01--12-00-00']);

        $this->methodFactory->runTask('backup', $hostConfig, $this->context);

        $files = $this->context->getResult('files', []);
        $types = array_column($files, 'type');
        $filePaths = array_column($files, 'file');

        $this->assertContains('db', $types, 'Backup should include database');
        $this->assertContains('files', $types, 'Backup should include files');

        // Check that db backup file has .sql.gz extension
        $dbFiles = array_filter($files, fn ($f) => 'db' === $f['type']);
        $dbFile = reset($dbFiles);
        $this->assertMatchesRegularExpression('/\.sql(\.gz)?$/', $dbFile['file'], 'DB backup should be a .sql or .sql.gz file');

        // Check that files backup has .tgz extension
        $tarFiles = array_filter($files, fn ($f) => 'files' === $f['type']);
        $tarFile = reset($tarFiles);
        $this->assertStringEndsWith('.tgz', $tarFile['file'], 'Files backup should be a .tgz file');
    }

    /**
     * Test full backup with db and files using restic strategy.
     * Simulates: phab backup db files.
     */
    public function testBackupDbAndFilesWithResticStrategy(): void
    {
        $hostConfig = $this->getHostConfigWithDryRunShell('hostWithDbAndRestic');

        $this->context->set('what', ['db', 'files']);
        $this->context->setResult('basename', ['test', '2024-01-01--12-00-00']);

        $this->methodFactory->runTask('backup', $hostConfig, $this->context);

        $files = $this->context->getResult('files', []);
        $types = array_column($files, 'type');

        $this->assertContains('db', $types, 'Backup should include database');
        $this->assertContains('restic', $types, 'Backup should include files via restic');
        $this->assertNotContains('files', $types, 'Should use restic, not tar-based files backup');

        // Check that db backup file has .sql.gz extension
        $dbFiles = array_filter($files, fn ($f) => 'db' === $f['type']);
        $dbFile = reset($dbFiles);
        $this->assertMatchesRegularExpression('/\.sql(\.gz)?$/', $dbFile['file'], 'DB backup should be a .sql or .sql.gz file');

        // Restic backup references the folder, not a specific file
        $resticFiles = array_filter($files, fn ($f) => 'restic' === $f['type']);
        $this->assertNotEmpty($resticFiles, 'Should have restic backup entries');
    }

    /**
     * Test that 'phab backup' (no arguments) collects correct backup methods.
     */
    public function testCollectBackupMethodsWithDbAndRestic(): void
    {
        $hostConfig = $this->configurationService->getHostConfig('hostWithDbAndRestic');

        $this->methodFactory->runTask('collectBackupMethods', $hostConfig, $this->context);

        $backupMethods = $this->context->getResult('backupMethods', []);

        $this->assertContains('db', $backupMethods, 'Should collect "db" as backup method');
        $this->assertContains('files', $backupMethods, 'Should collect "files" as backup method (not "restic")');
        $this->assertNotContains('restic', $backupMethods, '"restic" is how we backup, not what');
    }
}
