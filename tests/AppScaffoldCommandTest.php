<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27.
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\AppScaffoldCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AppScaffoldCommandTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion('3.0.0');
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->application->add(new AppScaffoldCommand($configuration, $method_factory));
    }

    /**
     * @group docker
     */
    public function testAppScaffolder()
    {
        $target_folder = $this->getTmpDir();
        if (!is_dir($target_folder)) {
            mkdir($target_folder);
        }

        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--short-name' => 'TST',
            '--name' => 'Test',
            '--output' => $target_folder,
            '--override' => true,
            'scaffold-url' => __DIR__.'/assets/scaffold-tests/scaffold-drupal-commerce.yml',
        ]);

        $this->checkScaffoldResults($target_folder);
    }

    /**
     * @group docker
     */
    public function testScaffoldWithRelativeFolder()
    {
        $root = $this->getTmpDir();
        $target_folder = $this->prepareTargetFolder($root);

        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--short-name' => 'TST',
            '--name' => 'Test',
            '--output' => '..',
            '--override' => true,
            'scaffold-url' => __DIR__.'/assets/scaffold-tests/scaffold-drupal-commerce.yml',
        ]);

        $this->checkScaffoldResults($target_folder);
    }

    public function testScaffoldQuestions()
    {
        $root = $this->getTmpDir();
        $this->prepareTargetFolder($root);

        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--short-name' => 'TST',
            '--name' => 'Test',
            '--output' => '..',
            '--override' => true,
            'scaffold-url' => __DIR__.'/assets/scaffold-tests/scaffold-simple.yml',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Project: Test', $output);
        $this->assertStringContainsString('Shortname: tst', $output);
    }

    public function testScaffoldSubfolder()
    {
        $root = $this->getTmpDir();
        $target_folder = $root.'/tmp/here';
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }

        chdir($target_folder);

        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--short-name' => 'TST',
            '--name' => 'Test',
            '--output' => '.',
            '--override' => true,
            'scaffold-url' => __DIR__.'/assets/scaffold-tests/scaffold-subfolder.yml',
        ]);

        $this->checkFileContent(
            $target_folder.'/test/web/modules/custom/tst_utils/tst_utils.info.yml',
            'name: Test utils module'
        );
        $this->checkFileContent(
            $target_folder.'/test/web/modules/custom/tst_utils/tst_utils.install',
            'function tst_utils_install()'
        );
    }

    public function testScaffoldExistingProjectFolder()
    {
        $root = $this->getTmpDir();
        $target_folder = $root.'/tmp/tst-test';
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }

        chdir($root.'/tmp');

        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--short-name' => 'TST',
            '--name' => 'Test',
            '--override' => true,
            'scaffold-url' => __DIR__.'/assets/scaffold-tests/scaffold-projectfolder.yml',
        ]);

        $this->checkFileContent(
            $target_folder.'/web/modules/custom/tst_utils/tst_utils.info.yml',
            'name: Test utils module'
        );
        $this->checkFileContent(
            $target_folder.'/web/modules/custom/tst_utils/tst_utils.install',
            'function tst_utils_install()'
        );
    }

    public function testErrorWhileScaffolding()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Scaffolding failed with exit-code 42');
        $root = $this->getTmpDir();
        $target_folder = $root.'/tmp/tst-test';
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
        }

        chdir($root.'/tmp');

        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--short-name' => 'TST',
            '--name' => 'Test',
            '--override' => true,
            'scaffold-url' => __DIR__.'/assets/scaffold-tests/scaffold-provoke-error.yml',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Project: Test', $output);
        // Here the exception should happen.
        $this->assertStringNotContainsString('Shortname: tst', $output);
    }

    protected function prepareTargetFolder(string $root): string
    {
        $target_folder = $root.'/tmp';
        if (!is_dir($target_folder)) {
            mkdir($target_folder, 0777, true);
            mkdir($target_folder.'/here');
        }

        chdir($target_folder.'/here');

        return $target_folder;
    }

    protected function checkScaffoldResults(string $target_folder): void
    {
        $this->checkFileContent($target_folder.'/test/.fabfile.yaml', 'name: Test');
        $this->checkFileContent($target_folder.'/test/.fabfile.yaml', 'key: tst');
        $this->checkFileContent($target_folder.'/test/.fabfile.yaml', 'host: test.test');
        $this->checkFileContent(
            $target_folder.'/test/web/modules/custom/tst_deploy/tst_deploy.info.yml',
            'name: Test deployment module'
        );
        $this->checkFileContent(
            $target_folder.'/test/web/modules/custom/tst_deploy/tst_deploy.info.yml',
            'name: Test deployment module'
        );
        $this->checkFileContent(
            $target_folder.'/test/web/modules/custom/tst_deploy/tst_deploy.install',
            'function tst_deploy_install()'
        );
    }
}
