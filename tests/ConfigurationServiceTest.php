<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\DrushMethod;
use Phabalicious\Method\GitMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\LoggerInterfaceTest;
use Symfony\Component\Console\Application;

class ConfigurationServiceTest extends TestCase
{

    /**
     * @var ConfigurationService
     */
    private $config;
    private $logger;

    public function setUp()
    {
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('3.0.0'));
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
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
        $result = $this->config->readConfiguration(getcwd(), getcwd() . '/assets/custom-fabfile-tests/custom_fabfile.yaml');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/custom-fabfile-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\FabfileNotFoundException
     */
    public function testNonExistingCustomFabfile()
    {
        $result = $this->config->readConfiguration(getcwd(), getcwd() . '/assets/custom__not_existing.yaml');
    }

    public function testRegularFabfile()
    {

        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubfolder()
    {
        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubFolder()
    {

        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1/folder2');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubSubFolder()
    {
        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1/folder2/folder3');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\FabfileNotFoundException
     */
    public function testNonExistingFabfile()
    {
        $result = $this->config->readConfiguration(getcwd() . '/assets/non-existing-fabfile-tests/one/two/three');
    }

    /**
     * @expectedException     \Phabalicious\Exception\MismatchedVersionException
     */
    public function testNonMatchingVersion()
    {
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('2.4.1'));

        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $config = new ConfigurationService($application, $logger);
        $result = $config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testGlobalInheritance()
    {
        $this->config->readConfiguration(getcwd() . '/assets/inherits');
        $this->assertEquals(123, $this->config->getSetting('fromFile1.value1'));
        $this->assertEquals(456, $this->config->getSetting('fromFile1.value2.value'));

        $this->assertEquals(123, $this->config->getSetting('fromFile3.value1'));
        $this->assertEquals(456, $this->config->getSetting('fromFile3.value2.value'));

        $this->assertEquals('a value', $this->config->getSetting('fromFile2.value1'));
        $this->assertEquals('another value', $this->config->getSetting('fromFile2.value2.value'));
    }

    public function testHostInheritance()
    {
        $this->config->readConfiguration(getcwd() . '/assets/inherits');
        $this->assertEquals('host-a', $this->config->getHostConfig('hostA')['host']);
        $this->assertEquals('user-a', $this->config->getHostConfig('hostA')['user']);
        $this->assertEquals('host-b', $this->config->getHostConfig('hostB')['host']);
        $this->assertEquals('user-b', $this->config->getHostConfig('hostB')['user']);
        $this->assertEquals(22, $this->config->getHostConfig('hostB')['port']);
    }

    public function testDockerHostInheritance()
    {
        $this->config->readConfiguration(getcwd() . '/assets/inherits');
        $this->assertEquals('dockerhost-a', $this->config->getDockerConfig('hostA')['host']);
        $this->assertEquals('user-a', $this->config->getDockerConfig('hostA')['user']);
        $this->assertEquals('dockerhost-b', $this->config->getDockerConfig('hostB')['host']);
        $this->assertEquals('user-b', $this->config->getDockerConfig('hostB')['user']);
    }

    public function testExecutables()
    {
        $this->config->getMethodFactory()->addMethod(new DrushMethod($this->logger));
        $this->config->getMethodFactory()->addMethod(new ScriptMethod($this->logger));
        $this->config->readConfiguration(getcwd() . '/assets/executables-tests');
        $this->assertEquals('/usr/bin/drush', $this->config->getHostConfig('unaltered')['executables']['drush']);
      $this->assertEquals('/usr/local/bin/drush', $this->config->getHostConfig('altered')['executables']['drush']);
        $this->assertEquals('/usr/bin/mysql', $this->config->getHostConfig('altered')['executables']['mysql']);
    }
}
