<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\BaseMethod;
use Phabalicious\Method\DrushMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\SshMethod;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Utilities\TestableLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Application;

class ConfigurationServiceTest extends PhabTestCase
{

    /**
     * @var ConfigurationService
     */
    private $config;
    /** @var TestableLogger */
    private $logger;

    public function setUp(): void
    {
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('3.0.0'));

        $logger = new TestableLogger();
        $this->logger = $logger;
        $this->config = new ConfigurationService($application, $logger);

        $method_factory = $this->getMockBuilder(MethodFactory::class)
            ->setMethods(['all'])
            ->disableOriginalConstructor()
            ->getMock();
        $method_factory->expects($this->any())
            ->method('all')
            ->will($this->returnValue([]));

        $this->config->setMethodFactory($method_factory);
    }

    public function testCustomFabfile()
    {
        $result = $this->config->readConfiguration(
            __DIR__,
            __DIR__ . '/assets/custom-fabfile-tests/custom_fabfile.yaml'
        );
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), __DIR__ . '/assets/custom-fabfile-tests');
    }

    public function testNonExistingCustomFabfile()
    {
        $this->expectException(\Phabalicious\Exception\FabfileNotFoundException::class);
        $result = $this->config->readConfiguration(
            __DIR__,
            __DIR__ . '/assets/custom__not_existing.yaml'
        );
    }

    public function testRegularFabfile()
    {

        $result = $this->config->readConfiguration(__DIR__ . '/assets/fabfile-hierarchy-tests');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), __DIR__ . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubfolder()
    {
        $result = $this->config->readConfiguration(__DIR__ . '/assets/fabfile-hierarchy-tests/folder1');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), __DIR__ . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubFolder()
    {

        $result = $this->config->readConfiguration(__DIR__ . '/assets/fabfile-hierarchy-tests/folder1/folder2');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), __DIR__ . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubSubFolder()
    {
        $result = $this->config->readConfiguration(
            __DIR__ . '/assets/fabfile-hierarchy-tests/folder1/folder2/folder3'
        );
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), __DIR__ . '/assets/fabfile-hierarchy-tests');
    }

    public function testNonExistingFabfile()
    {
        $this->expectException(\Phabalicious\Exception\FabfileNotFoundException::class);
        $result = $this->config->readConfiguration(
            __DIR__ . '/assets/non-existing-fabfile-tests/one/two/three'
        );
    }

    public function testNonMatchingVersion()
    {
        $this->expectException(\Phabalicious\Exception\MismatchedVersionException::class);
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('2.4.1'));

        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $config = new ConfigurationService($application, $logger);
        $result = $config->readConfiguration(__DIR__ . '/assets/fabfile-hierarchy-tests');
    }

    public function testGlobalInheritance()
    {
        $this->config->readConfiguration(__DIR__ . '/assets/inherits');
        $this->assertEquals(123, $this->config->getSetting('fromFile1.value1'));
        $this->assertEquals(456, $this->config->getSetting('fromFile1.value2.value'));

        $this->assertEquals(123, $this->config->getSetting('fromFile3.value1'));
        $this->assertEquals(456, $this->config->getSetting('fromFile3.value2.value'));

        $this->assertEquals('a value', $this->config->getSetting('fromFile2.value1'));
        $this->assertEquals('another value', $this->config->getSetting('fromFile2.value2.value'));
    }

    public function testDeprecatedInheritance()
    {
        $this->config->readConfiguration(__DIR__ . '/assets/inherits');
        $host_config = $this->config->getHostConfig('hostDeprecated');
        $this->assertTrue($this->logger->containsMessage(LogLevel::WARNING, 'Please use a newer version of this file'));
    }

    public function testHostInheritance()
    {
        $this->config->readConfiguration(__DIR__ . '/assets/inherits');
        $this->assertEquals('host-a', $this->config->getHostConfig('hostA')['host']);
        $this->assertEquals('user-a', $this->config->getHostConfig('hostA')['user']);
        $this->assertEquals('host-b', $this->config->getHostConfig('hostB')['host']);
        $this->assertEquals('user-b', $this->config->getHostConfig('hostB')['user']);
        $this->assertEquals(22, $this->config->getHostConfig('hostB')['port']);
    }

    public function testDockerHostInheritance()
    {
        $this->config->readConfiguration(__DIR__ . '/assets/inherits');
        $this->assertEquals('dockerhost-a', $this->config->getDockerConfig('hostA')['host']);
        $this->assertEquals('user-a', $this->config->getDockerConfig('hostA')['user']);
        $this->assertEquals('dockerhost-b', $this->config->getDockerConfig('hostB')['host']);
        $this->assertEquals('user-b', $this->config->getDockerConfig('hostB')['user']);
    }

    public function testExecutables()
    {
        $this->config->getMethodFactory()->addMethod(new MysqlMethod($this->logger));
        $this->config->getMethodFactory()->addMethod(new DrushMethod($this->logger));
        $this->config->getMethodFactory()->addMethod(new ScriptMethod($this->logger));
        $this->config->readConfiguration(__DIR__ . '/assets/executables-tests');
        $this->assertEquals('/usr/bin/drush', $this->config->getHostConfig('unaltered')['executables']['drush']);
        $this->assertEquals(
            '/usr/local/bin/drush',
            $this->config->getHostConfig('altered')['executables']['drush']
        );
        $this->assertEquals('/usr/bin/mysql', $this->config->getHostConfig('altered')['executables']['mysql']);
    }

    public function testSshTunnelConfiguration()
    {
        $this->config->getMethodFactory()->addMethod(new SshMethod($this->logger));
        $this->config->getMethodFactory()->addMethod(new ScriptMethod($this->logger));
        $this->config->readConfiguration(__DIR__ . '/assets/sshtunnel-tests');
        $ssh_tunnel = $this->config->getHostConfig('unaltered')->get('sshTunnel');
        $this->assertEquals('1.2.3.4', $ssh_tunnel['destHost']);
        $this->assertEquals('1234', $ssh_tunnel['destPort']);
        $this->assertEquals('2.3.4.5', $ssh_tunnel['bridgeHost']);
        $this->assertEquals('5432', $ssh_tunnel['bridgePort']);
    }

    public function testMissingRemoteYamlOfflineMode()
    {

        $this->expectException("Phabalicious\Exception\FabfileNotReadableException");

        $this->config->getMethodFactory()->addMethod(new LocalMethod($this->logger));
        $this->config->getMethodFactory()->addMethod(new ScriptMethod($this->logger));
        $this->config->readConfiguration(__DIR__ . '/assets/remote-yaml-tests');
        $this->config->setStrictRemoteHandling(true);
        $this->config->setOffline(true);

        $config = $this->config->getHostConfig("test");
    }

    public function testMissingRemoteYaml()
    {
        $this->expectException("Phabalicious\Exception\FabfileNotReadableException");

        $this->config->getMethodFactory()->addMethod(new LocalMethod($this->logger));
        $this->config->getMethodFactory()->addMethod(new ScriptMethod($this->logger));
        $this->config->readConfiguration(__DIR__ . '/assets/remote-yaml-tests');
        $this->config->setStrictRemoteHandling(true);

        $config = $this->config->getHostConfig("test");
    }

    public function testResolveInheritanceRefs()
    {
        $data = new Node([
            'inheritsFrom' => './one.yml',
            'foo' => [
                'inheritsFrom' => '../../two.yml',
            ],
            'bar' => [
                'inheritsFrom' => '@/three.yml'
            ],
        ], 'foo bar');

        $this->config->resolveRelativeInheritanceRefs(
            $data,
            'https://example.com',
            'https://two.example.com/foo/bar'
        );

        $this->assertEquals('https://two.example.com/foo/bar/one.yml', $data->getProperty('inheritsFrom.0'));
        $this->assertEquals('https://two.example.com/two.yml', $data->getProperty('foo.inheritsFrom.0'));
        $this->assertEquals('https://example.com/three.yml', $data->getProperty('bar.inheritsFrom.0'));

        $data = new Node([
            'inheritsFrom' => './one.yml',
            'foo' => [
                'inheritsFrom' => '../../two.yml',
            ],
            'bar' => [
                'inheritsFrom' => '@/three.yml'
            ],
        ], 'foo bar');

        $this->config->resolveRelativeInheritanceRefs(
            $data,
            '/home/somewhere/else',
            '/home/foo/bar'
        );

        $this->assertEquals('/home/foo/bar/one.yml', $data->getProperty('inheritsFrom.0'));
        $this->assertEquals('/home/two.yml', $data->getProperty('foo.inheritsFrom.0'));
        $this->assertEquals('/home/somewhere/else/three.yml', $data->getProperty('bar.inheritsFrom.0'));

        $data = new Node([
            'inheritsFrom' => './one.yml',
            'foo' => [
                'inheritsFrom' => '../../two.yml',
            ],
            'bar' => [
                'inheritsFrom' => '@/three.yml'
            ],
        ], 'foo bar');

        $this->config->resolveRelativeInheritanceRefs(
            $data,
            '..//somewhere/else',
            '../config/public'
        );

        $this->assertEquals('../config/public/one.yml', $data->getProperty('inheritsFrom.0'));
        $this->assertEquals('../two.yml', $data->getProperty('foo.inheritsFrom.0'));
        $this->assertEquals('../somewhere/else/three.yml', $data->getProperty('bar.inheritsFrom.0'));
    }
}
