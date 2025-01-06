<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScottyCtlCreateOptions;
use Phabalicious\Method\ScottyMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ScottyMethodTest extends PhabTestCase
{
    private ScottyMethod $method;

    private ConfigurationService $configurationService;

    private TaskContext $context;

    public function setUp(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new ScottyMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod($this->method);

        $this->configurationService->readConfiguration(__DIR__.'/assets/scotty-tests/nginx/fabfile.yaml');

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)
                ->disableOriginalConstructor()
                ->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);
    }

    public function testConfigValidation(): void
    {
        $this->expectException(ValidationFailedException::class);
        $host_config = $this->configurationService->getHostConfig('invalid');
    }

    public function testConfigInheritance(): void
    {
        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->assertEquals('http://localhost:21342', $host_config['scotty']['server']);

        $host_config = $this->configurationService->getHostConfig('hostB');
        $this->assertEquals('http://scotty:21342', $host_config['scotty']['server']);
    }

    public function testScaffold(): void
    {
        $base_dir = $this->getTmpDir('scotty-tests');
        $this->context->set('installDir', $base_dir);
        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->method->scaffoldApp($host_config, $this->context);

        $docker_compose = Yaml::parseFile($base_dir.'/docker-compose.yaml');

        $this->assertEquals('my-deepest-secret', $docker_compose['services']['nginx']['environment']['APP_SECRET']);
    }

    public function testScottyCtlCreateOptions(): void
    {
        $host_config = $this->configurationService->getHostConfig('hostA');

        $options = new ScottyCtlCreateOptions($host_config, $this->context);
        $result = $options->build('create', ['app_folder' => '/app/folder']);
        $this->assertEquals([
            '--server',
            'http://localhost:21342',
            '--access-token',
            'hello-world',
            'create',
            'phab-scotty-test',
            '--folder',
            '/app/folder',
            '--service',
            'nginx:80',
            '--env',
            'APP_SECRET=my-deepest-secret',
            '--basic-auth',
            'admin:admin',
            '--app-blueprint',
            'nginx-lagoon',
            '--registry',
            'factorial'], $result);
    }

    public function testScottyCtlCreateOptions2(): void
    {
        $host_config = $this->configurationService->getHostConfig('hostC');

        $options = new ScottyCtlCreateOptions($host_config, $this->context);
        $result = $options->build('create', ['app_folder' => '/app/folder']);
        $this->assertEquals([
            '--server',
            'http://localhost:21342',
            '--access-token',
            'hello-world',
            'create',
            'phab-scotty-test',
            '--folder',
            '/app/folder',
            '--service',
            'nginx:80',
            '--env',
            'APP_SECRET=my-deepest-secret',
            '--custom-domain',
            'example.com:nginx',
            '--basic-auth',
            'admin:admin',
            '--app-blueprint',
            'nginx-lagoon',
            '--registry',
            'factorial',
            '--ttl',
            '1h',
            '--allow-robots'], $result);
    }
}
