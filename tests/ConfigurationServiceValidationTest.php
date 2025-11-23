<?php

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\GitMethod;
use Phabalicious\Method\MethodFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;

class ConfigurationServiceValidationTest extends PhabTestCase
{
    /**
     * @var ConfigurationService
     */
    private $config;
    private $logger;

    public function setUp(): void
    {
        $application = $this->getMockBuilder(Application::class)
            ->onlyMethods(['getVersion'])
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
        $this->config->readConfiguration(__DIR__.'/assets/validation-tests');
    }

    public function testGlobalSettingsFromMethod()
    {
        $this->assertEquals($this->config->getSetting('gitOptions.pull'), ['--no-edit', '--rebase']);
    }

    public function testMethodDefaultSettings()
    {
        $host = $this->config->getHostConfig('mbb');
        $this->assertEquals($host['gitRootFolder'], $host['rootFolder']);
    }
}
