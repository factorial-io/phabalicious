<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

class ConfigurationServiceTest extends TestCase
{
    private $config;

    public function setUp() {
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('3.0.0'));
        $this->config = new ConfigurationService($application);
    }

    public function testCustomFabfile() {
        $result = $this->config->readConfiguration(getcwd(), getcwd() . '/assets/custom-fabfile-tests/custom_fabfile.yaml');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/custom-fabfile-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\FabfileNotFoundException
     */
    public function testNonExistingCustomFabfile() {
        $result = $this->config->readConfiguration(getcwd(), getcwd() . '/assets/custom__not_existing.yaml');
    }

    public function testRegularFabfile() {

        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubfolder() {
        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubFolder() {

        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1/folder2');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubSubFolder() {
        $result = $this->config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1/folder2/folder3');
        $this->assertTrue($result);
        $this->assertEquals($this->config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\FabfileNotFoundException
     */
    public function testNonExistingFabfile() {
        $result = $this->config->readConfiguration(getcwd() . '/assets/non-existing-fabfile-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\MismatchedVersionException
     */
    public function testNonMatchingVersion() {
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('2.4.1'));
        $config = new ConfigurationService($application);
        $result = $config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests');
    }
}
