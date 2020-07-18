<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\WebhookMethod;
use Phabalicious\Method\TaskContext;
use Psr\Log\AbstractLogger;
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


    public function setUp()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->getMock();
        $this->method = new WebhookMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new WebhookMethod($logger));

        $this->configurationService->readConfiguration($this->getcwd() . '/assets/webhook-tests/fabfile.yaml');

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

    public function testPostAndGetWebhook()
    {
        $host_config = $this->configurationService->getHostConfig('hostA');
        $result = $this->method->runWebhook('testDelete', $host_config, $this->context);
        $this->assertEquals(204, $result->getStatusCode());

        $result = $this->method->runWebhook('testPost', $host_config, $this->context);
        $this->assertEquals(200, $result->getStatusCode());
        $body = (string) $result->getBody();

        $result = $this->method->runWebhook('testGet', $host_config, $this->context);
        $this->assertEquals(200, $result->getStatusCode());
        $body = (string) $result->getBody();
        $json = json_decode($body);

        $this->assertNotEmpty($json[0]->body, 'Response body is empty');
        $payload = json_decode($json[0]->body);

        $this->assertEquals("This is var1 from hostA", $payload->var1);
        $this->assertEquals("This is global settings var 2", $payload->var2);
    }
}
