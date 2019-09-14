<?php /** @noinspection PhpParamsInspection */

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 23.09.18
 * Time: 13:17
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\GitMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GitMethodTest extends PhabTestCase
{
    /** @var GitMethod */
    private $method;

    /** @var ConfigurationService */
    private $configurationService;

    /** @var TaskContext */
    private $context;

    /**
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function setUp()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->getMock();
        $this->method = new GitMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod($this->method);

        $this->configurationService->readConfiguration($this->getcwd() . '/assets/git-tests/fabfile.yaml');

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);
    }

    private function setupRepo(HostConfig $host_config)
    {
        $shell = $host_config->shell();
        $shell->cd($host_config['gitRootFolder']);
        $shell->run('rm -rf .git');
        $shell->run('git init . ');
        $shell->run('git config user.email "phabalicious@factorial.io"');
        $shell->run('git add fabfile.yaml');
        $shell->run('git commit -m "initial commit"');
        $shell->run('git tag -a 1.0.0 -m "Tagging version 1.0.0"');

        return $shell;
    }

    private function cleanupRepo(HostConfig $host_config)
    {
        $shell = $host_config->shell();
        $shell->cd($host_config['gitRootFolder']);
        $shell->run('rm -rf .git');
    }

    public function testGetVersion()
    {
        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->setupRepo($host_config);

        $this->assertEquals('1.0.0', $this->method->getVersion($host_config, $this->context));
        $this->assertTrue($this->method->isWorkingcopyClean($host_config, $this->context));
    }

    public function testDirtyWorkingCopy()
    {
        $host_config = $this->configurationService->getHostConfig('hostA');
        $shell = $this->setupRepo($host_config);
        $shell->run('touch dummy.txt');
        $shell->run('git add dummy.txt');
        $shell->run('git commit -m "Add dummy.txt"');
        $shell->run('rm dummy.txt');

        $this->assertFalse($this->method->isWorkingcopyClean($host_config, $this->context));
    }

    public function tearDown()
    {
        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->cleanupRepo($host_config);
    }
}
