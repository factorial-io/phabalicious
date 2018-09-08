<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\SshMethod;
use Phabalicious\Validation\ValidationErrorBag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class SshMethodTest extends TestCase
{

    /**
     * @var SshMethod
     */
    private $method;

    public function setUp()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $this->method = new SshMethod($logger);
    }

    public function testValidConfig()
    {
        $errors = new ValidationErrorBag();

        $this->method->validateConfig([
            'user' => 'testuser',
            'host' => 'localhost',
            'port' => 22,
        ], $errors);
        $this->assertEquals($errors->hasErrors(), false);

    }

    public function testInvalidConfig()
    {
        $errors = new ValidationErrorBag();

        $this->method->validateConfig([
            'host' => 'localhost'
        ], $errors);
        $this->assertEquals($errors->hasErrors(), true);
        $this->assertEquals(['user', 'port'], $errors->getKeysWithErrors(), '', 0.0, 10, true);
    }

    public function testInValidTunnelConfig()
    {
        $errors = new ValidationErrorBag();

        $this->method->validateConfig([
            'host' => 'localhost',
            'user' => 'user',
            'port' => '22',
            'sshTunnel' => [
                'bridgeHost' => 'localhost',
                'bridgeUser' => 'user',
            ],
        ], $errors);
        $this->assertEquals(true, $errors->hasErrors());
        $this->assertEquals(['bridgePort', 'destPort', 'destHost', 'localPort'], $errors->getKeysWithErrors(), '', 0.0, 10, true);
    }


    public function testGetDefaultConfig()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $configuration_service = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = [
            'config_name' => 'test',
            'user' => 'user',
            'host' => 'host',
            'sshTunnel' => [
            ]
        ];
        $result = $this->method->getDefaultConfig($configuration_service, $config);
        $this->assertArrayHasKey('port', $result);
        $this->assertArrayHasKey('disableKnownHosts', $result);
        $this->assertArrayHasKey('sshTunnel', $result);
        $this->assertArrayHasKey('localPort', $result['sshTunnel']);
        $this->assertEquals($result['port'], $result['sshTunnel']['localPort']);

        // Running it again should give the same SSH-Port
        $result2 = $this->method->getDefaultConfig($configuration_service, $config);
        $this->assertEquals($result['port'], $result2['port']);

    }

    public function testGetDefaultConfigWithDocker()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $configuration_service = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = [
            'config_name' => 'test',
            'user' => 'user',
            'host' => 'host',
            'docker' => [
                'name' => 'test',
            ],
            'sshTunnel' => [
            ]
        ];
        $result = $this->method->getDefaultConfig($configuration_service, $config);
        $this->assertArrayHasKey('destHostFromDockerContainer', $result['sshTunnel']);
        $this->assertEquals($config['docker']['name'], $result['sshTunnel']['destHostFromDockerContainer']);

    }
}
