<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Validation\ValidationErrorBag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class DrushMethodTest extends TestCase
{
    /** @var DrushMethod */
    private $method;

    /** @var ConfigurationService */
    private $configurationService;

    public function setup()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->getMock();
        $this->method = new DrushMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod($this->method);

        $this->configurationService->readConfiguration(getcwd() . '/assets/drush-tests/fabfile.yaml');

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
        $result = $this->method->getDefaultConfig($this->configurationService, $host_config);

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
        $result = $this->method->getDefaultConfig($this->configurationService, $host_config);

        $this->assertEquals(7, $result['drupalVersion']);
        $this->assertEquals(8, $result['drushVersion']);
    }

    public function testDrush8Need()
    {
        $host_config = [
            'needs' => ['drush8'],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, $host_config);

        $this->assertEquals(8, $result['drupalVersion']);
        $this->assertEquals(8, $result['drushVersion']);
    }

    public function testDrush9Need()
    {
        $host_config = [
            'needs' => ['drush9'],
        ];
        $result = $this->method->getDefaultConfig($this->configurationService, $host_config);

        $this->assertEquals(8, $result['drupalVersion']);
        $this->assertEquals(9, $result['drushVersion']);
    }

    public function testValidateConfig()
    {
        $host_config = [
            'needs' => ['drush7'],
        ];
        $errors = new ValidationErrorBag();
        $result = $this->method->validateConfig($host_config, $errors);

        $this->assertEquals(1, count($errors->getWarnings()));
        $this->assertContains('drush7', $errors->getWarnings()['needs']);
        $this->assertContains('deprecated', $errors->getWarnings()['needs']);
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
}
