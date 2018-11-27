<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\AppScaffoldCommand;
use Phabalicious\Command\GetPropertyCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AppScaffoldCommandTest extends TestCase
{
    /** @var Application */
    protected $application;

    public function setup()
    {
        $this->application = new Application();
        $this->application->setVersion('3.0.0');
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->application->add(new AppScaffoldCommand($configuration, $method_factory));
    }

    public function testAppScaffolder()
    {
        $command = $this->application->find('app:scaffold');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            '--short-name'  => 'TST',
            '--name' => 'Test',
            '--output' => '/tmp',
            '--override' => true,
            'scaffold-url' => getcwd() . '/assets/scaffold-tests/scaffold-drupal-commerce.yml'
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->checkFileContent('/tmp/test/.fabfile.yaml', 'name: Test');
        $this->checkFileContent('/tmp/test/.fabfile.yaml', 'key: tst');
        $this->checkFileContent('/tmp/test/.fabfile.yaml', 'host: test.test');
        $this->checkFileContent(
            '/tmp/test/web/modules/custom/tst_deploy/tst_deploy.info.yml',
            'name: Test deployment module'
        );
        $this->checkFileContent(
            '/tmp/test/web/modules/custom/tst_deploy/tst_deploy.info.yml',
            'name: Test deployment module'
        );
        $this->checkFileContent(
            '/tmp/test/web/modules/custom/tst_deploy/tst_deploy.install',
            'function tst_deploy_install()'
        );
    }

    private function checkFileContent($filename, $needle)
    {

        $haystack = file_get_contents($filename);
        $this->assertContains($needle, $haystack);
    }
}
