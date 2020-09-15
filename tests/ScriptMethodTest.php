<?php /** @noinspection PhpParamsInspection */

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptMethodTest extends PhabTestCase
{

    /** @var ScriptMethod */
    private $method;

    /** @var ConfigurationService */
    private $configurationService;

    /** @var TaskContext */
    private $context;

    private $savedArguments;

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
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

        $this->configurationService->readConfiguration($this->getcwd() . '/assets/script-tests/fabfile.yaml');

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);
    }

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingHostConfigException
     * @throws MissingScriptCallbackImplementation
     * @throws ShellProviderNotFoundException
     * @throws ValidationFailedException
     * @throws UnknownReplacementPatternException
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
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingHostConfigException
     * @throws MissingScriptCallbackImplementation
     * @throws ShellProviderNotFoundException
     * @throws UnknownReplacementPatternException
     * @throws ValidationFailedException
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
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingHostConfigException
     * @throws MissingScriptCallbackImplementation
     * @throws ShellProviderNotFoundException
     * @throws UnknownReplacementPatternException
     * @throws ValidationFailedException
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
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingHostConfigException
     * @throws MissingScriptCallbackImplementation
     * @throws ShellProviderNotFoundException
     * @throws UnknownReplacementPatternException
     * @throws ValidationFailedException
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
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingHostConfigException
     * @throws MissingScriptCallbackImplementation
     * @throws ShellProviderNotFoundException
     * @throws UnknownReplacementPatternException
     * @throws ValidationFailedException
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
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws MissingHostConfigException
     * @throws MissingScriptCallbackImplementation
     * @throws ShellProviderNotFoundException
     * @throws UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    public function testExpandedEnvironmentVariablesFromHostConfig()
    {
        $this->context->set('scriptData', [
            'echo "$ROOT_FOLDER"',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(
            [$this->getcwd() . '/assets/script-tests'],
            $this->context->getCommandResult()->getOutput()
        );
    }


    public function testMissingCallbackImplementation()
    {
        $this->expectException(MissingScriptCallbackImplementation::class);

        $this->context->set('callbacks', [
            'debug' => [$this, 'missingScriptDebugCallback'],
        ]);

        $this->context->set('scriptData', [
            'debug(hello world)',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->method->runScript($host_config, $this->context);
    }

    public function testParsingCallbackParameters()
    {
        $this->context->set('callbacks', [
            'debug' => [$this, 'saveArgumentsCallback'],
        ]);

        $this->context->set('scriptData', [
            'debug(hello world)',
            'debug("hello world")',
            'debug("hello", "world")',
            'debug("hello, world", "Foo, bar")',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->method->runScript($host_config, $this->context);

        $this->assertEquals(["hello world"], $this->savedArguments[0]);
        $this->assertEquals(["hello world"], $this->savedArguments[1]);
        $this->assertEquals(["hello", "world"], $this->savedArguments[2]);
        $this->assertEquals(["hello, world", "Foo, bar"], $this->savedArguments[3]);
    }

    public function saveArgumentsCallback($context, ...$args)
    {
        $this->savedArguments[] = $args;
    }

    public function testTaskSpecificScripts()
    {
        $this->context->set('callbacks', [
            'debug' => [$this, 'scriptDebugCallback'],
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->preflightTask('deploy', $host_config, $this->context);
        $this->method->fallback('deploy', $host_config, $this->context);
        $this->method->postflightTask('deploy', $host_config, $this->context);

        $this->assertEquals([
            'deployPrepare on dev',
            'deployPrepare on hostA',
            'deploy on dev',
            'deploy on hostA',
            'deployFinished on dev',
            'deployFinished on hostA'
        ], $this->context->get('debug'));
    }

    public function scriptDebugCallback(TaskContextInterface $context, $message)
    {
        $debug =  $context->get('debug');
        if (empty($debug)) {
            $debug = [];
        }
        $debug[] = $message;
        $context->set('debug', $debug);
    }
}
