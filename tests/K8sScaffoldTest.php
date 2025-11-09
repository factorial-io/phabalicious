<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27.
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\K8sCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\K8sMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

class K8sScaffoldTest extends PhabTestCase
{
    protected Application $application;

    protected ConfigurationService $configuration;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = new ConsoleLogger(new ConsoleOutput());

        $this->configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($this->configuration, $logger);
        $method_factory->addMethod(new K8sMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $this->configuration->readConfiguration(__DIR__.'/assets/k8s-command/fabfile.yaml');

        $this->application->add(new K8sCommand($this->configuration, $method_factory));
    }

    public function testK8sScaffold(): void
    {
        chdir($this->configuration->getFabfilePath());
        system('echo "hello world" > kube/should-not-exist.yml');
        $command = $this->application->find('k8s');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'k8s' => ['scaffold'],
            '--config' => 'test',
        ]);

        $yaml = Yaml::parseFile('kube/deployment.yml');
        $this->assertEquals('foo', $yaml['data']['valueA']);
        $this->assertEquals('bar', $yaml['data']['valueB']);

        $this->assertFileDoesNotExist('kube/should-not-exist.yml');
    }

    public function testK8sScaffoldOverridden(): void
    {
        chdir($this->configuration->getFabfilePath());
        $command = $this->application->find('k8s');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'k8s' => ['scaffold'],
            '--config' => 'test-overridden',
        ]);

        $yaml = Yaml::parseFile('kube/deployment.yml');
        $this->assertEquals('foobar', $yaml['data']['valueA']);
        $this->assertEquals('baz', $yaml['data']['valueB']);
    }

    public function testK8sScaffoldWithNameiInQuestion(): void
    {
        chdir($this->configuration->getFabfilePath());
        $command = $this->application->find('k8s');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'k8s' => ['scaffold'],
            '--config' => 'test-name-in-question',
        ]);

        $yaml = Yaml::parseFile('kube/deployment.yml');
        $this->assertEquals('foobar', $yaml['data']['valueA']);
        $this->assertEquals('baz', $yaml['data']['valueB']);
    }
}
