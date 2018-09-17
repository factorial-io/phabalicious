<?php /** @noinspection PhpParamsInspection */

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 16.09.18
 * Time: 14:36
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptMethodTest extends TestCase
{

    /** @var ScriptMethod */
    private $method;

    /** @var ConfigurationService */
    private $configurationService;

    /** @var TaskContext */
    private $context;

    /**
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     */
    public function setUp()
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(\Symfony\Component\Console\Application::class)->getMock();
        $this->method = new ScriptMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->configurationService->readConfiguration(getcwd() . '/assets/script-tests/fabfile.yaml');

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock());
        $this->context->setConfigurationService($this->configurationService);
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function testRunScript()
    {
        $this->context->set('scriptData', [
            'echo "hello"',
            'echo "world"',
            'echo "hello world"'
        ]);

        $this->method->runScript($this->configurationService->getHostConfig('hostA'), $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(0, $this->context->getCommandResult()->getExitCode());
        $this->assertEquals(['hello world'], $this->context->getCommandResult()->getOutput());
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function testExitOnExitCode()
    {
        $this->context->set('scriptData', [
            '(exit 42)',
            '(exit 0)'
        ]);

        $this->method->runScript($this->configurationService->getHostConfig('hostA'), $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(42, $this->context->getCommandResult()->getExitCode());
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function testIgnoreExitCode()
    {
        $this->context->set('scriptData', [
            'breakOnFirstError(0)',
            '(exit 42)',
            '(exit 0)',
            'breakOnFirstError(1)'
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(0, $this->context->getCommandResult()->getExitCode());
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function testEnvironmentVariables()
    {
        $this->context->set('environment', [
            'TEST_VAR' => '42',
        ]);
        $this->context->set('scriptData', [
            'echo $TEST_VAR',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(['42'], $this->context->getCommandResult()->getOutput());
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function testExpandedEnvironmentVariables()
    {
        $this->context->set('environment', [
            'TEST_VAR' => '%host.testEnvironmentVar%',
        ]);
        $this->context->set('scriptData', [
            'echo "$TEST_VAR"',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(['testEnvironmentVar from hostA'], $this->context->getCommandResult()->getOutput());
    }

    /**
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function testExpandedEnvironmentVariablesFromHostConfig()
    {
        $this->context->set('scriptData', [
            'echo "$ROOT_FOLDER"',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals([getcwd() . '/assets/script-tests'], $this->context->getCommandResult()->getOutput());
    }

}
