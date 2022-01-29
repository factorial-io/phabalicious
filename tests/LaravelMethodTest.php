<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\LaravelMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\ScriptMethod;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;

class LaravelMethodTest extends PhabTestCase
{
    /** @var \Phabalicious\Method\DrushMethod */
    private $method;

    /** @var ConfigurationService */
    private $configurationService;

    public function setup(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new LaravelMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new MysqlMethod($logger));
        $method_factory->addMethod($this->method);

        $this->configurationService->readConfiguration(__DIR__ . '/assets/laravel-tests/fabfile.yaml');
    }

    public function testGetDefaultConfig()
    {
        $host_config = [
            'rootFolder' => '.',
            'needs' => ['laravel'],
            'artisanTasks' => [
                'reset' => ['mycustomresettask']
            ],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, new Node($host_config, 'code'));

        $this->assertArrayHasKey('reset', $result['artisanTasks']);
        $this->assertArrayHasKey('install', $result['artisanTasks']);
        $this->assertEquals(['mycustomresettask'], $result['artisanTasks']['reset']);
    }

    public function testCustomArtisanTasks()
    {
        $result = $this->configurationService->getHostConfig('test-custom-artisan-tasks');
        $this->assertArrayHasKey('reset', $result['artisanTasks']);
        $this->assertArrayHasKey('install', $result['artisanTasks']);
        $this->assertEquals(['mycustomresettask'], $result['artisanTasks']['reset']);
        $this->assertEquals(['mycustominstalltask'], $result['artisanTasks']['install']);
    }

    public function testDefaultCustomArtisanTasks()
    {
        $result = $this->configurationService->getHostConfig('test-default-custom-artisan-tasks');
        $this->assertArrayHasKey('reset', $result['artisanTasks']);
        $this->assertArrayHasKey('install', $result['artisanTasks']);
        $this->assertEquals(['mycustomdefaultresettask'], $result['artisanTasks']['reset']);
        $this->assertEquals(['mycustominstalltask'], $result['artisanTasks']['install']);
    }
}
