<?php

/** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\DrushMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Validation\ValidationErrorBag;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;

class DrushMethodTest extends PhabTestCase
{
    /** @var DrushMethod */
    private $method;

    /** @var ConfigurationService */
    private $configurationService;

    public function setUp(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new DrushMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new MysqlMethod($logger));
        $method_factory->addMethod($this->method);

        $this->configurationService->readConfiguration(__DIR__.'/assets/drush-tests/fabfile.yaml');
    }

    public function testGetDefaultConfig()
    {
        $host_config = [
            'needs' => ['drush'],
            'database' => [
                'user' => 'drupal',
                'pass' => 'drupal',
            ],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, new Node($host_config, 'code'));

        $this->assertEquals(8, $result['drupalVersion']);
        $this->assertEquals(8, $result['drushVersion']);
        $this->assertEquals(true, $result['revertFeatures']);

        $this->assertArrayHasKey('host', $result['database']);
    }

    public function testDrush7Need()
    {
        $host_config = [
            'needs' => ['drush7'],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, new Node($host_config, 'code'));

        $this->assertEquals(7, $result['drupalVersion']);
        $this->assertEquals(8, $result['drushVersion']);
    }

    public function testDrush8Need()
    {
        $host_config = [
            'needs' => ['drush8'],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, new Node($host_config, 'code'));

        $this->assertEquals(8, $result['drupalVersion']);
        $this->assertEquals(8, $result['drushVersion']);
    }

    public function testDrush9Need()
    {
        $host_config = [
            'needs' => ['drush9'],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, new Node($host_config, 'code'));

        $this->assertEquals(8, $result['drupalVersion']);
        $this->assertEquals(9, $result['drushVersion']);
    }

    public function testValidateConfig()
    {
        $host_config = [
            'needs' => ['drush7'],
            'configName' => 'test',
        ];
        $errors = new ValidationErrorBag();
        $this->method->validateConfig($this->configurationService, new Node($host_config, ''), $errors);

        $this->assertEquals(1, count($errors->getWarnings()));
        $this->assertStringContainsString('drush7', $errors->getWarnings()['needs']);
        $this->assertStringContainsString('deprecated', $errors->getWarnings()['needs']);
    }

    public function testGetHostConfig()
    {
        $host_config = $this->configurationService->getHostConfig('test');

        $this->assertEquals(false, $host_config['revertFeatures']);
        $this->assertArrayHasKey('host', $host_config['database']);
        $this->assertEquals('localhost', $host_config['database']['host']);
        $this->assertEquals('/var/www/sites/default', $host_config['siteFolder']);
        $this->assertEquals('/var/www/sites/default/files', $host_config['filesFolder']);
    }

    public function testConfigurationManagementSettings()
    {
        $configuration_management = $this->configurationService->getHostConfig('unaltered')['configurationManagement'];

        $this->assertEquals('drush status', $configuration_management['sync'][0]);
        $this->assertEquals('drush status', $configuration_management['prod'][0]);
        $this->assertArrayNotHasKey('staging', $configuration_management);

        $configuration_management = $this->configurationService->getHostConfig('altered')['configurationManagement'];

        $this->assertEquals('drush status', $configuration_management['staging'][0]);
        $this->assertArrayNotHasKey('sync', $configuration_management);
        $this->assertArrayNotHasKey('prod', $configuration_management);
    }

    public function testDrushUnneededMethodDependency()
    {
        $config = $this->configurationService->getHostConfig('method-dependency');

        $this->assertEquals(
            [],
            $this->method->getMethodDependencies($this->configurationService->getMethodFactory(), $config->getData())
        );
    }

    public function testDrushMethodDependency()
    {
        $this->assertEquals(
            ['mysql'],
            $this->method->getMethodDependencies($this->configurationService->getMethodFactory(), new Node([
                'needs' => ['drush'],
            ], ''))
        );
    }
}
