<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{

    public function testCustomFabfile() {
        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd(), getcwd() . '/assets/custom-fabfile-tests/custom_fabfile.yaml');
        $this->assertTrue($result);
        $this->assertEquals($config->getFabfilePath(), getCwd() . '/assets/custom-fabfile-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\FabfileNotFoundException
     */
    public function testNonExistingCustomFabfile() {
        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd(), getcwd() . '/assets/custom__not_existing.yaml');
    }

    public function testRegularFabfile() {

        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests');
        $this->assertTrue($result);
        $this->assertEquals($config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubfolder() {
        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1');
        $this->assertTrue($result);
        $this->assertEquals($config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubFolder() {

        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1/folder2');
        $this->assertTrue($result);
        $this->assertEquals($config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    public function testRegularFabfileInSubSubSubFolder() {
        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd() . '/assets/fabfile-hierarchy-tests/folder1/folder2/folder3');
        $this->assertTrue($result);
        $this->assertEquals($config->getFabfilePath(), getCwd() . '/assets/fabfile-hierarchy-tests');
    }

    /**
     * @expectedException     \Phabalicious\Exception\FabfileNotFoundException
     */
    public function testNonExistingFabfile() {
        $config = new ConfigurationService();
        $result = $config->readConfiguration(getcwd() . '/assets/non-existing-fabfile-tests');
    }
}
