<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\SshMethod;
use Phabalicious\Validation\ValidationErrorBag;
use Psr\Log\AbstractLogger;

class SshMethodTest extends PhabTestCase
{
    /**
     * @var SshMethod
     */
    private $method;

    public function setup(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $this->method = new SshMethod($logger);
    }

    public function testValidConfig()
    {
        $errors = new ValidationErrorBag();

        $config = new Node([
            'user' => 'testuser',
            'host' => 'localhost',
            'port' => 22,
            'rootFolder' => '/',
            'shellExecutable' => '/usr/bin/ssh',
            'configName' => 'test',
        ], '');
        $this->method->createShellProvider([])->validateConfig($config, $errors);
        $this->assertEquals($errors->hasErrors(), false);
    }

    public function testInvalidConfig()
    {
        $errors = new ValidationErrorBag();

        $this->method->createShellProvider([])->validateConfig(new Node([
            'host' => 'localhost',
            'configName' => 'test',
        ], ''), $errors);
        $this->assertEquals(true, $errors->hasErrors());
        $this->assertEqualsCanonicalizing(
            ['user', 'port', 'rootFolder', 'rootFolder', 'shellExecutable'],
            $errors->getKeysWithErrors()
        );
    }

    public function testInvalidRootFolderName()
    {
        $errors = new ValidationErrorBag();

        $this->method->createShellProvider([])->validateConfig(new Node([
            'host' => 'localhost',
            'configName' => 'test',
            'rootFolder' => '/some/rootFolder/',
        ], ''), $errors);
        $this->assertEquals(true, $errors->hasErrors());
        $this->assertEqualsCanonicalizing(
            ['user', 'port', 'rootFolder', 'shellExecutable'],
            $errors->getKeysWithErrors()
        );
    }

    public function testInValidTunnelConfig()
    {
        $errors = new ValidationErrorBag();

        $this->method->createShellProvider([])->validateConfig(new Node([
            'configName' => 'test',
            'host' => 'localhost',
            'user' => 'user',
            'port' => '22',
            'rootFolder' => '/',
            'shellExecutable' => '/usr/bin/ssh',
            'sshTunnel' => [
                'bridgeHost' => 'localhost',
                'bridgeUser' => 'user',
            ],
        ], ''), $errors);
        $this->assertEquals(true, $errors->hasErrors());
        $this->assertEqualsCanonicalizing(
            ['bridgePort', 'destPort', 'destHost', 'localPort'],
            $errors->getKeysWithErrors()
        );
    }

    public function testGetDefaultConfig()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $configuration_service = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = new Node([
            'configName' => 'test',
            'user' => 'user',
            'host' => 'host',
            'sshTunnel' => [
            ],
        ], '');
        $shell_provider = $this->method->createShellProvider([]);
        $result = $shell_provider->getDefaultConfig($configuration_service, $config);
        $this->assertArrayHasKey('port', $result);
        $this->assertArrayHasKey('disableKnownHosts', $result);
        $this->assertArrayHasKey('sshTunnel', $result);
        $this->assertArrayHasKey('localPort', $result['sshTunnel']);
        $this->assertEquals($result['port'], $result['sshTunnel']['localPort']);

        // Running it again should give the same SSH-Port
        $result2 = $shell_provider->getDefaultConfig($configuration_service, $config);
        $this->assertEquals($result['port'], $result2['port']);
    }

    public function testGetDefaultConfigWithDocker()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $configuration_service = $this->getMockBuilder(ConfigurationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config = new Node([
            'config_name' => 'test',
            'user' => 'user',
            'host' => 'host',
            'docker' => [
                'name' => 'test',
            ],
            'sshTunnel' => [
            ],
        ], '');
        $shell_provider = $this->method->createShellProvider([]);
        $result = $shell_provider->getDefaultConfig($configuration_service, $config);
        $this->assertArrayHasKey('destHostFromDockerContainer', $result['sshTunnel']);
        $this->assertEquals($config['docker']['name'], $result['sshTunnel']['destHostFromDockerContainer']);
    }
}
