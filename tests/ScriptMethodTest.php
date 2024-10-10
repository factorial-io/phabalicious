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
use Phabalicious\Method\ScriptExecutionContext;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptMethodTest extends PhabTestCase
{

    /** @var ScriptMethod */
    private ScriptMethod $method;

    /** @var ConfigurationService */
    private ConfigurationService $configurationService;

    /** @var TaskContext */
    private TaskContext $context;

    /**
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
     */
    public function setUp(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new ScriptMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->configurationService->readConfiguration(__DIR__ . '/assets/script-tests/fabfile.yaml');

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
    public function testRunScript(): void
    {
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
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
    public function testExitOnExitCode(): void
    {
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
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
    public function testIgnoreExitCode(): void
    {
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
            'break_on_first_error(0)',
            '(exit 42)',
            '(exit 0)',
            'break_on_first_error(1)'
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
    public function testEnvironmentVariables(): void
    {
        $this->context->set('environment', [
            'TEST_VAR' => '42',
        ]);
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
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
    public function testExpandedEnvironmentVariables(): void
    {
        $this->context->set('environment', [
            'TEST_VAR' => '%host.testEnvironmentVar%',
        ]);
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
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
    public function testExpandedEnvironmentVariablesFromHostConfig(): void
    {
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
            'echo "$ROOT_FOLDER"',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(
            [__DIR__ . '/assets/script-tests'],
            $this->context->getCommandResult()->getOutput()
        );
    }


    public function testMissingCallbackImplementation(): void
    {
        $this->expectException(MissingScriptCallbackImplementation::class);

        $this->context->set(ScriptMethod::SCRIPT_CALLBACKS, [
            'debug' => [$this, 'missingScriptDebugCallback'],
        ]);

        $this->context->set(ScriptMethod::SCRIPT_DATA, [
            'debug(hello world)',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->method->runScript($host_config, $this->context);
    }

    public function testParsingCallbackParameters(): void
    {
        $callback = new DebugCallback(true);
        $this->context->set(ScriptMethod::SCRIPT_CALLBACKS, [
            $callback::getName() => $callback,
        ]);

        $this->context->set(ScriptMethod::SCRIPT_DATA, [
            'debug(hello world)',
            'debug("hello world")',
            'debug("hello", "world")',
            'debug("hello, world", "Foo, bar")',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');
        $this->method->runScript($host_config, $this->context);



        $this->assertEquals(["hello world"], $callback->debugOutput[0]);
        $this->assertEquals(["hello world"], $callback->debugOutput[1]);
        $this->assertEquals(["hello", "world"], $callback->debugOutput[2]);
        $this->assertEquals(["hello, world", "Foo, bar"], $callback->debugOutput[3]);
    }

    public function testTaskSpecificScripts(): void
    {
        $callback = new DebugCallback(false);
        $this->context->set(ScriptMethod::SCRIPT_CALLBACKS, [
            $callback::getName() => $callback,
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
        ], $callback->debugOutput);
    }


    public function testValidateReplacements(): void
    {

        $this->assertTrue(Utilities::validateReplacements([
            "kjhdakadjh\%blaa\%bla"
        ]));
        $this->assertTrue(Utilities::validateReplacements([
            "\%blaa\%bla"
        ]));
        $this->assertTrue(Utilities::validateReplacements([
            "bla\%blaa\%"
        ]));

        $error = Utilities::validateReplacements([
            "lhkjdhkadhj",
            "%here%",
            "khjkhjkjhkjh",
        ]);

        $this->assertNotTrue($error);
        $this->assertEquals(1, $error->getLineNumber());
        $this->assertEquals('%here%', $error->getFailedPattern());


        $error = Utilities::validateReplacements([
            "lhkjdhkadhj",
            "huhu %here%",
            "khjkhjkjhkjh",
        ]);

        $this->assertNotTrue($error);
        $this->assertEquals(1, $error->getLineNumber());
        $this->assertEquals(' %here%', $error->getFailedPattern());

        $error = Utilities::validateReplacements([
            "lhkjdhkadhj",
            "khjkhjkjhkjh",
            "%here%haha",
        ]);

        $this->assertNotTrue($error);
        $this->assertEquals(2, $error->getLineNumber());
        $this->assertEquals('%here%', $error->getFailedPattern());

        $error = Utilities::validateReplacements([
            "lhkjdhkadhj",
            "%here%%huhu%",
            "khjkhjkjhkjh",
        ]);

        $this->assertNotTrue($error);
        $this->assertFalse($error->getMissingArgument());
        $this->assertEquals(1, $error->getLineNumber());
        $this->assertEquals('e%%', $error->getFailedPattern());
        $this->assertEquals('%here%%huhu%', $error->getFailedLine());

        $error = Utilities::validateReplacements([
            "lhkjdhkadhj",
            "there is an missing argument here: %arguments.foobar% and more text",
            "khjkhjkjhkjh",
        ]);

        $this->assertNotTrue($error);
        $this->assertEquals("foobar", $error->getMissingArgument());
        $this->assertEquals(1, $error->getLineNumber());
        $this->assertEquals(' %arguments.foobar%', $error->getFailedPattern());

        $this->assertTrue(Utilities::validateReplacements([
            "lhkjdhkadhj",
            "echo %secret.googlemaps_api_dev_key%",
            "khjkhjkjhkjh",
        ]));

        $this->assertTrue(Utilities::validateReplacements([
            "lhkjdhkadhj",
            "echo smtp-password is //%secret.smtp-password%//",
            "khjkhjkjhkjh",
        ]));

        $this->assertTrue(Utilities::validateReplacements([
            "lhkjdhkadhj",
            "echo smtp-password is //\%secret.smtp-password%//",
            "khjkhjkjhkjh",
        ]));
    }

    /**
     * @group docker
     */
    public function testScriptRunInDockerContext(): void
    {
        $this->context->set(ScriptMethod::SCRIPT_CONTEXT, ScriptExecutionContext::DOCKER_IMAGE);
        $this->context->set(ScriptMethod::SCRIPT_CONTEXT_DATA, ['image' => 'busybox']);
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
            'env',
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $output = $this->context->getCommandResult()->getOutput();

        $this->assertContains("PHAB_SUB_SHELL=1", $output);
    }


    public function testCleanupScriptSection(): void
    {
        $this->context->set(ScriptMethod::SCRIPT_DATA, [
            '(exit 42)',
        ]);
        $this->context->set(ScriptMethod::SCRIPT_CLEANUP, [
            'echo "$ROOT_FOLDER"'
        ]);

        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->method->runScript($host_config, $this->context);

        $this->assertNotNull($this->context->getCommandResult());
        $this->assertEquals(
            [__DIR__ . '/assets/script-tests'],
            $this->context->getCommandResult()->getOutput()
        );
    }
}
