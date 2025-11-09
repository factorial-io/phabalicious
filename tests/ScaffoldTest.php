<?php

/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27.
 */

namespace Phabalicious\Tests;

use Defuse\Crypto\Crypto;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ScaffoldTest extends PhabTestCase
{
    public const PASSWORD = 'my-secret-#21';

    protected Application $application;

    protected ConfigurationService $configuration;

    private LoggerInterface $logger;

    public function setup(): void
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $this->logger = $logger = new ConsoleLogger(new ConsoleOutput());

        $this->configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($this->configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));

        $this->configuration->setSetting('secrets', [
            'test-encryption' => ['question' => 'whats the password'],
        ]);
        $this->configuration->getPasswordManager()->setSecret('test-encryption', self::PASSWORD);
    }

    private function createContext(): TaskContext
    {
        $context = new TaskContext(
            null,
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $context->setConfigurationService($this->configuration);
        $context->setIo($this->getMockBuilder(SymfonyStyle::class)->disableOriginalConstructor()->getMock());

        return $context;
    }

    public function testVersionCheck(): void
    {
        $this->expectException(MismatchedVersionException::class);
        $scaffolder = new Scaffolder($this->configuration);
        $options = new Options();
        $options->setCompabilityVersion('2.0');

        $context = $this->createContext();
        $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/version-check.yml',
            $this->getTmpDir(),
            $context,
            [],
            $options
        );
    }

    public function testDataOverride(): void
    {
        $scaffolder = new Scaffolder($this->configuration);
        $context = $this->createContext();
        $context->set('dataOverrides', [
            'questions' => [],
            'assets' => [],
        ]);
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        $result = $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/override.yml',
            $this->getTmpDir(),
            $context,
            [
                'name' => 'test-overrides',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());
    }

    public function testAlterFile(): void
    {
        $scaffolder = new Scaffolder($this->configuration);
        $context = $this->createContext();
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        $result = $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/alter-file.yml',
            $this->getTmpDir(),
            $context,
            [
                'name' => 'test-alter',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());

        $json = json_decode(file_get_contents($this->getTmpDir().'/test-alter/output.json'), false, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('b-overridden', $json->b);
        $this->assertEquals('d-overridden', $json->c->d);

        $yaml = Yaml::parseFile($this->getTmpDir().'/test-alter/output.yaml');

        $this->assertEquals('b-overridden', $yaml['b']);
        $this->assertEquals('d-overridden', $yaml['c']['d']);
        $this->assertTrue($yaml['c']['test_bool']);
        $this->assertIsBool($yaml['c']['test_bool']);
        $this->assertEquals(123, $yaml['c']['test_int']);
        $this->assertIsInt($yaml['c']['test_int']);

        $this->assertEquals('a string', $yaml['c']['test_string']);
        $this->assertIsString($yaml['c']['test_string']);
    }

    /**
     * @group docker
     */
    public function testScaffoldCallback(): void
    {
        $scaffolder = new Scaffolder($this->configuration);
        $context = $this->createContext();
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        $result = $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/scaffold-callback.yml',
            $this->getTmpDir(),
            $context,
            [
                'name' => 'test-scaffold-callback',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());

        $json = json_decode(file_get_contents($this->getTmpDir().'/test-scaffold-callback/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('phabalicious/helloworld', $json['name']);
        $this->assertArrayHasKey('phpro/grumphp-shim', $json['require-dev']);
        $this->assertArrayHasKey('drupal/coder', $json['require-dev']);

        $json = json_decode(file_get_contents($this->getTmpDir().'/test-scaffold-callback/second/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('phpro/grumphp-shim', $json['require-dev']);
        $this->assertArrayHasKey('drupal/coder', $json['require-dev']);
    }

    public function runTestTwigExtensions(ShellProviderInterface $shell, TaskContextInterface $context, $dir): void
    {
        $scaffolder = new Scaffolder($this->configuration);
        $options = new Options();
        $options->setAllowOverride(true)
            ->setShell($shell)
            ->setUseCacheTokens(false);

        $result = $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/scaffold-twig.yml',
            $dir,
            $context,
            [
                'name' => 'test-scaffold-twig',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());

        $content = $shell->getFileContents($dir.'/test-scaffold-twig/test-twig.json', $context);
        $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('A-test-string-to-be-slugified', $json['slug']);
        $this->assertEquals('db42607fd51c90a4d9c49130f1fe98a8', $json['md5']);
        $decrypted = Crypto::decryptWithPassword($json['encrypted'], self::PASSWORD);
        $this->assertEquals('hello world', $decrypted);
        $this->assertEquals('hello world', $json['decrypted']);
    }

    public function testLocalTwigExtension(): void
    {
        $context = $this->createContext();
        $shell = new LocalShellProvider($this->logger);
        $host_config = new HostConfig([
            'rootFolder' => $this->getTmpDir(),
            'shellExecutable' => '/bin/bash',
        ], $shell, $this->configuration);
        $shell->setHostConfig($host_config);

        $this->runTestTwigExtensions($shell, $context, $this->getTmpDir());
    }

    public function testRemoteScaffolding(): void
    {
        $shell = $this->getDockerizedSshShell($this->logger, $this->configuration);
        $context = $this->createContext();
        $context->setShell($shell);

        $this->runTestTwigExtensions($shell, $context, '/app');
    }

    public function testScaffoldLocalFiles(): void
    {
        $scaffolder = new Scaffolder($this->configuration);
        $context = $this->createContext();
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        $result = $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/scaffold-files.yml',
            $this->getTmpDir(),
            $context,
            [
                'name' => 'test-scaffold-files',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());

        $this->compareScaffoldedFiles(
            __DIR__.'/assets/scaffolder-test',
            $this->getTmpDir().'/test-scaffold-files/'
        );
    }

    public function testScaffoldLocalEncryptedFiles(): void
    {
        $scaffolder = new Scaffolder($this->configuration);
        $context = $this->createContext();
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        $context->getPasswordManager()->setSecret('my-secret', 'my-secret');
        $result = $scaffolder->scaffold(
            __DIR__.'/assets/scaffolder-test/scaffold-encrypted-files.yml',
            $this->getTmpDir(),
            $context,
            [
                'name' => 'test-scaffold-encrypted-files',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());

        $this->compareScaffoldedFiles(
            __DIR__.'/assets/scaffolder-test',
            $this->getTmpDir().'/test-scaffold-encrypted-files/'
        );
    }

    private function compareScaffoldedFiles($source_dir, $target_dir): void
    {
        for ($i = 1; $i < 4; ++$i) {
            $filename = sprintf('test_%d.txt', $i);
            $this->assertFileEquals(
                $source_dir.'/files/'.$filename,
                $target_dir.$filename
            );
        }

        for ($i = 1; $i < 4; ++$i) {
            $filename = sprintf('test_%d.bin', $i);
            $this->assertFileEquals(
                $source_dir.'/binary/'.$filename,
                $target_dir.$filename
            );
        }
    }
}
