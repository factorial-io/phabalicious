<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\GitMethod;
use Phabalicious\Method\MethodFactory;
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

    public function setUp() {
        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['getVersion'])
            ->getMock();
        $application->expects($this->any())
            ->method('getVersion')
            ->will($this->returnValue('3.0.0'));
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $this->logger = $logger;
        $this->config = new ConfigurationService($application, $logger);

        $method_factory = new MethodFactory($this->config, $this->logger);
        $method_factory->addMethod(new GitMethod($this->logger));

        $this->config->setMethodFactory($method_factory);
        $this->config->readConfiguration(getcwd() . '/assets/validation-tests');
    }



    public function testGlobalSettingsFromMethod()
    {

        $this->assertEquals($this->config->getSetting('gitOptions.pull'), ['--no-edit', '--rebase']);
    }

    public function testMethodDefaultSettings() {
       $host = $this->config->getHostConfig('mbb');

       $this->assertEquals($host['gitRootFolder'], $host['rootFolder']);

    }
}
