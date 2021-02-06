<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 05.10.18
 * Time: 12:27
 */

namespace Phabalicious\Tests;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContext;
use Phabalicious\Scaffolder\Options;
use Phabalicious\Scaffolder\Scaffolder;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ScaffoldTest extends PhabTestCase
{
    /** @var Application */
    protected $application;

    /** @var ConfigurationService  */
    protected $configuration;

    public function setup()
    {
        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($this->configuration, $logger);
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));
    }

    private function createContext()
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


    public function testVersionCheck()
    {
        $this->expectException('Phabalicious\Exception\MismatchedVersionException');
        $scaffolder = new Scaffolder($this->configuration);
        $options = new Options();
        $options->setCompabilityVersion("2.0");

        $context = $this->createContext();
        $scaffolder->scaffold(
            $this->getCwd() . '/assets/scaffolder-test/version-check.yml',
            $this->getcwd(),
            $context,
            [],
            $options
        );
    }

    public function testDataOverride()
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
            $this->getCwd() . '/assets/scaffolder-test/override.yml',
            $this->getcwd(),
            $context,
            [
                'name' => 'test-overrides',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());
    }

    public function testAlterFile()
    {

        $scaffolder = new Scaffolder($this->configuration);
        $context = $this->createContext();
        $options = new Options();
        $options->setAllowOverride(true)
            ->setUseCacheTokens(false);

        $result = $scaffolder->scaffold(
            $this->getCwd() . '/assets/scaffolder-test/alter-file.yml',
            $this->getcwd(),
            $context,
            [
                'name' => 'test-alter',
            ],
            $options
        );

        $this->assertEquals(0, $result->getExitCode());

        $json = json_decode(file_get_contents($this->getCwd() . '/test-alter/output.json'));

        $this->assertEquals("b-overridden", $json->b);
        $this->assertEquals("d-overridden", $json->c->d);

        $yaml = Yaml::parseFile($this->getCwd() . '/test-alter/output.yaml');

        $this->assertEquals("b-overridden", $yaml['b']);
        $this->assertEquals("d-overridden", $yaml['c']['d']);
    }
}
