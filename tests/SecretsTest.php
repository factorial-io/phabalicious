<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\OutputCommand;
use Phabalicious\Command\ScriptCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SecretsTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($configuration, $logger);
        $method_factory->addMethod(new LocalMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));

        $configuration->readConfiguration(__DIR__ . '/assets/secret-tests/fabfile.yaml');

        $this->application->add(new ScriptCommand($configuration, $method_factory));
        $this->application->add(new OutputCommand($configuration, $method_factory));
        $this->application->add(new ScriptCommand($configuration, $method_factory));

        putenv("SMTP_PASSWORD");
        putenv("MARIADB_PASSWORD");
        putenv("OP_PASSWORD");
    }


    public function testSecretsBlueprintAsArguments()
    {

        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--blueprint' => 'test',
            '--what' => 'host',
            '--config' => 'testBlueprint',
            '--secret' => [ 'mysql-password=top_Secret', 'smtp-password=$leet%', 'op-password=foobar']
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('top_Secret', $output);
        $this->assertStringContainsString('123--top_Secret--321', $output);
        $this->assertStringContainsString('--top_Secret--$leet%--', $output);
        $this->assertStringNotContainsString('%secret.mysql-password', $output);
    }

    public function testSecretsAsArguments()
    {

        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--what' => 'host',
            '--config' => 'testHost',
            '--secret' => [ 'mysql-password=top_Secret', 'smtp-password=$leet%', 'op-password=foobar']
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('top_Secret', $output);
        $this->assertStringContainsString('123--top_Secret--321', $output);
        $this->assertStringContainsString('--top_Secret--$leet%--', $output);
        $this->assertStringNotContainsString('%secret.mysql-password', $output);
    }

    public function testSecretsAsCustomEnvironmentVar()
    {

        putenv("SMTP_PASSWORD=top_Secret");
        putenv("MARIADB_PASSWORD=top_Secret");
        putenv("OP_PASSWORD=foobar");
        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--what' => 'host',
            '--config' => 'testHost',
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('top_Secret', $output);
        $this->assertStringContainsString('123--top_Secret--321', $output);
        $this->assertStringNotContainsString('%secret.mysql-password', $output);
    }

    public function testSecretsAsEnvironmentVar()
    {

        putenv("SMTP_PASSWORD=top_Secret");
        putenv("MARIADB_PASSWORD=top_Secret");
        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--what' => 'host',
            '--config' => 'testEnv',
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('top_Secret', $output);
        $this->assertStringContainsString('123--top_Secret--321', $output);
        $this->assertStringNotContainsString('%secret.smtp-password', $output);
        putenv("SMTP_PASSWORD");
    }
    /**
     * @dataProvider provideTestScriptNames
     */
    public function testSecretsInScripts($script_name)
    {

        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'script' => $script_name,
            '--config' => 'testHost',
            '--secret' => [ 'mysql-password=top_Secret', 'smtp-password=\$leet%', 'op-password=foobar']
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('mysql-password is //top_Secret//', $output);
        $this->assertStringContainsString('database-password is //top_Secret//', $output);
        $this->assertStringContainsString('smtp-password is //$leet%//', $output);
        $this->assertStringContainsString('op-password is //foobar//', $output);
        $this->assertStringNotContainsString('%secret.mysql-password', $output);
    }

    public function provideTestScriptNames()
    {
        return [
            ['test:secrets:1'],
            ['test:secrets:2'],
            ['test:secrets:3'],
        ];
    }

    /**
     * @group docker
     * @group 1password
     */
    public function testSecretsFrom1Password()
    {

        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--what' => 'host',
            '--config' => 'test1Password',
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('iamsosecret', $output);
        $this->assertStringContainsString('123--iamsosecret--321', $output);
        $this->assertStringNotContainsString('%secret.op-password', $output);
    }

    /**
     * @group docker
     * @group 1password
     */
    public function testSecretReferenceFrom1Password()
    {

        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--what' => 'host',
            '--config' => 'test1PasswordRef',
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('iamsosecretref', $output);
        $this->assertStringContainsString('123--iamsosecretref--321', $output);
        $this->assertStringNotContainsString('%secret.op-password-reference', $output);
    }

    public function testUnknownSecret()
    {

        $this->expectException("Phabalicious\Exception\UnknownSecretException");
        $command = $this->application->find('output');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            '--what' => 'host',
            '--config' => 'testUnknownSecret',
            '--secret' => [ 'mysql-password=top_Secret']
        ));

        $output = $commandTester->getDisplay();
    }

    /**
     * @group docker
     * @group 1password
     */
    public function testGetFileFrom1Password()
    {

        $command = $this->application->find('script');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'script' => 'test',
            '--config' => 'testGetFileFrom1Password',
        ));

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Hello world', $output);
        $this->assertStringContainsString('Please do not delete it', $output);
    }
}
