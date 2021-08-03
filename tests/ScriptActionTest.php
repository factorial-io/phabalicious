<?php

namespace Phabalicious\Tests;

use Phabalicious\Artifact\Actions\Base\ScriptAction;
use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\TestableLogger;
use Phabalicious\Validation\ValidationErrorBag;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class ScriptActionTest extends TestCase
{

    /**
     * @var \Phabalicious\Configuration\HostConfig
     */
    private $hostConfig;

    /**
     * @var \Phabalicious\Method\TaskContext
     */
    private $context;

    public function setUp()
    {
        $app = $this->getMockBuilder(Application::class)
            ->getMock();
        $logger = new TestableLogger();

        $config  = new ConfigurationService($app, $logger);
        $config->setMethodFactory(new MethodFactory($config, $logger));

        $config->getMethodFactory()->addMethod(new ScriptMethod($logger));

        $shellProvider = new LocalShellProvider($logger);
        $this->hostConfig = new HostConfig([
            'configName' => 'test',
            'rootFolder' => getcwd() . '/tests',
            'shellExecutable' => '/bin/bash',
        ], $shellProvider, $config);

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->set('installDir', getcwd());
        $this->context->set('targetDir', getcwd());
        $this->context->setConfigurationService($config);
    }

    /**
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    protected function createAction(array $arguments): ScriptAction
    {
        $action = new ScriptAction();
        $errors = new ValidationErrorBag();
        $action->validateConfig($this->hostConfig, [
            'action' => 'script',
            'arguments' => $arguments
        ], $errors);
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }
        $action->setArguments($arguments);

        return $action;
    }

    public function testSimpleScript()
    {
        $action = $this->createAction([
            'echo "hello world"',
        ]);

        $action->run($this->hostConfig, $this->context);
        $output = $this->context->getCommandResult()->getOutput();

        $this->assertEquals(1, count($output));
        $this->assertEquals("hello world", $output[0]);
    }

    public function testSimpleScriptInHostContext()
    {
        $action = $this->createAction([
            'script' => [
                'env',
                'echo "hello world"',
            ],
            'context' => 'host',
        ]);

        $action->run($this->hostConfig, $this->context);
        $output = $this->context->getCommandResult()->getOutput();

        $this->assertEquals(1, count($output));
        $this->assertEquals("hello world", $output[0]);
    }

    public function testScriptWithUnknownContext()
    {
        $this->expectException(ValidationFailedException::class);

        $action = $this->createAction([
           "script" => [
               'echo "hello world"',
           ],
            "context" => 'dockerx',
            "image" => "busybox"
        ]);
    }

    /**
     * @group docker
     */
    public function testScriptInDockerContext()
    {
        $action = $this->createAction([
            "script" => [
                'env',
            ],
            "context" => 'docker',
            "image" => "busybox"
        ]);

        $action->run($this->hostConfig, $this->context);
        $output = $this->context->getCommandResult()->getOutput();

        $this->assertContains("PHAB_SUB_SHELL=1", $output);
    }

    /**
     * @group docker
     */
    public function testNodeVersionInDockerContext()
    {
        $action = $this->createAction([
            "script" => [
                'node --version',
            ],
            "context" => 'docker',
            "image" => "node:14"
        ]);

        $action->run($this->hostConfig, $this->context);
        $output = $this->context->getCommandResult()->getOutput();

        $this->assertContains("v14.", $output[0]);
    }

    /**
     * @group docker
     */
    public function testNpmInstallInDockerContext()
    {
        $action = $this->createAction([
            "script" => [
                'npm config set prefix /app',
                'npm install -g gulp-cli',
                'npm install',
                '/app/bin/gulp --tasks'
            ],
            "context" => 'docker',
            "image" => "node:14"
        ]);


        $context = clone $this->context;

        $context->set('installDir', getcwd() . '/tests/assets/script-action-npm-install');
        $context->set('targetDir', getcwd() . '/tests/assets/script-action-npm-install');

        $action->run($this->hostConfig, $context);
        $output = $context->getCommandResult()->getOutput();

        $this->assertContains("Tasks for /app/gulpfile.js", $output[0]);
    }
}
