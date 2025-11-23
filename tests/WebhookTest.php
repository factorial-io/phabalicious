<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\WebhookMethod;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookTest extends PhabTestCase
{
    private $method;

    private $configurationService;

    /**
     * @var TaskContext
     */
    private $context;

    public function setUp(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new WebhookMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new WebhookMethod($logger));

        $this->configurationService->readConfiguration(__DIR__.'/assets/webhook-tests/fabfile.yaml');

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);
    }

    public function testNonExistingWebhook()
    {
        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->assertFalse($this->method->runWebhook('testUnavailableWebhook', $host_config, $this->context));
    }
}
