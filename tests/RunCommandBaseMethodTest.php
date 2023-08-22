<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\ComposerMethod;
use Phabalicious\Method\DockerMethod;
use Phabalicious\Method\LaravelMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\NpmMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\YarnMethod;
use Phabalicious\Validation\ValidationErrorBag;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommandBaseMethodTest extends PhabTestCase
{
    /** @var ConfigurationService */
    private $configurationService;

    /**
     * @var \Phabalicious\Method\TaskContext
     */
    protected $context;

    /**
     * @var mixed|\PHPUnit\Framework\MockObject\MockObject|\Psr\Log\AbstractLogger
     */
    private $logger;

    /**
     * @var \Phabalicious\Method\MethodFactory
     */
    private $methodFactory;

    public function setup(): void
    {
        $this->logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->configurationService = new ConfigurationService($app, $this->logger);

        $method_factory = new MethodFactory($this->configurationService, $this->logger);
        $method_factory->addMethod(new ScriptMethod($this->logger));
        $method_factory->addMethod(new YarnMethod($this->logger));
        $method_factory->addMethod(new NpmMethod($this->logger));
        $method_factory->addMethod(new ComposerMethod($this->logger));
        $method_factory->addMethod(new LaravelMethod($this->logger));
        $method_factory->addMethod(new DockerMethod($this->logger));

        $this->methodFactory = $method_factory;

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);


        $this->configurationService->readConfiguration(__DIR__ . '/assets/run-command-tests/fabfile.yaml');
    }


    /**
     * @dataProvider methodNameProvider
     */
    public function testDeprecatedConfig($method_name, $has_build_command)
    {
        $host_config = [
            "{$method_name}BuildCommand" => 'build',
            "{$method_name}RunContext" => 'host',
            "{$method_name}RootFolder" => '/foo/bar',
        ];

        $errors = new ValidationErrorBag();
        $class_name = "Phabalicious\\Method\\" . ucwords($method_name) . "Method";
        $method = new $class_name($this->logger);
        $method->validateConfig($this->configurationService, new Node($host_config, 'test'), $errors);
        if ($has_build_command) {
            $this->assertArrayHasKey("{$method_name}BuildCommand", $errors->getWarnings());
        }
        $this->assertArrayHasKey("{$method_name}RunContext", $errors->getWarnings());
        $this->assertArrayHasKey("{$method_name}RootFolder", $errors->getWarnings());
    }

    /**
     * @dataProvider methodNameProvider
     */
    public function testDeprecationsStillAvailable($method_name, $has_build_command, $docker_image)
    {
        $host_config = $this->configurationService->getHostConfig($method_name . '-deprecated');

        if ($has_build_command) {
            $this->assertEquals('build:prod', $host_config->getProperty("{$method_name}.buildCommand"));
        }
        $this->assertEquals('docker-image', $host_config->getProperty("{$method_name}.context"));
        $this->assertEquals($docker_image, $host_config->getProperty("image"));
        $this->assertEquals('/foo/bar', $host_config->getProperty("{$method_name}.rootFolder"));
    }

    /**
     * @dataProvider hostConfigDataProvider
     * @group docker
     */
    public function testYarnRunCommand($config, $yarn_run_context)
    {
        $host_config = $this->configurationService->getHostConfig($config);

        $this->assertEquals($yarn_run_context, $host_config->getProperty('yarn.context'));
        $this->context->set('command', 'info react');
        $this->methodFactory->getMethod('yarn')->yarn($host_config, $this->context);
        $result = $this->context->getCommandResult();

        $this->assertEquals(0, $result->getExitCode());

        $payload = implode("\n", $result->getOutput());
        $this->assertStringContainsString("name: 'react'", $payload);
    }


    public function hostConfigDataProvider(): array
    {
        return [
            ['inside-docker-image-on-docker-host', 'docker-image-on-docker-host'],
            ['on-host', 'host'],
            ['on-docker-host', 'docker-host'],
            ['inside-docker-image', 'docker-image'],
        ];
    }

    public function methodNameProvider()
    {
        return [
            ['yarn', true, 'node:16'],
            ['npm', true, 'node:16'],
            ['composer', false, 'composer'],
            ['laravel', false, 'php'],
        ];
    }
}
