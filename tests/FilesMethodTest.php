<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\FilesMethod;
use Phabalicious\Method\GitMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\AbstractLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FilesMethodTest extends PhabTestCase
{
    use ProphecyTrait;

    /** @var FilesMethod */
    private FilesMethod $method;

    /** @var ConfigurationService */
    private ConfigurationService $configurationService;

    /** @var TaskContext */
    private TaskContext $context;

    /**
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function setUp(): void
    {
        $logger = $this->getMockBuilder(AbstractLogger::class)->getMock();
        $app = $this->getMockBuilder(Application::class)->getMock();
        $this->method = new FilesMethod($logger);
        $this->configurationService = new ConfigurationService($app, $logger);

        $method_factory = new MethodFactory($this->configurationService, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod($this->method);

        $this->configurationService->readConfiguration(__DIR__ . '/assets/files-tests/fabfile.yaml');

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setConfigurationService($this->configurationService);
    }

    public function testPutFileWithRelativeFile(): void
    {
        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->context->set('sourceFile', 'foobar.txt');
        $this->context->set('destinationFile', '../../foobaz.txt');
        $mocked_shell = $this->prophesize(ShellProviderInterface::class);
        $mocked_shell->putFile(Argument::any(), Argument::any(), $this->context)->willReturn(true);

        $this->context->set('shell', $mocked_shell);
        $this->method->putFile($host_config, $this->context);

        $this->assertEquals('/var/foobaz.txt', $this->context->getResult('targetFile'));
    }

    public function testPutFileWithAbsoluteFile(): void
    {
        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->context->set('sourceFile', 'foobar.txt');
        $this->context->set('destinationFile', '/var/www/foobaz.txt');
        $mocked_shell = $this->prophesize(ShellProviderInterface::class);
        $mocked_shell->putFile(Argument::any(), Argument::any(), $this->context)->willReturn(true);

        $this->context->set('shell', $mocked_shell);
        $this->method->putFile($host_config, $this->context);

        $this->assertEquals('/var/www/foobaz.txt', $this->context->getResult('targetFile'));
    }

    public function testGetFileWithRelativeFile(): void
    {
        $host_config = $this->configurationService->getHostConfig('hostA');

        $this->context->set('sourceFile', '../foobar.txt');
        $this->context->set('destFile', 'foobaz.txt');
        $mocked_shell = $this->prophesize(ShellProviderInterface::class);
        $mocked_shell->getFile(Argument::any(), Argument::any(), $this->context)->willReturn(true);

        $this->context->set('shell', $mocked_shell);
        $this->method->getFile($host_config, $this->context);

        $this->assertEquals('/var/www/foobar.txt', $this->context->getResult('sourceFile'));
    }
}
