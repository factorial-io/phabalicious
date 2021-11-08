<?php
/**
 * Created by PhpStorm.
 * User: stephan
 * Date: 10.09.18
 * Time: 22:03
 */

namespace Phabalicious\Tests;

use Phabalicious\Command\DrushCommand;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\DrushMethod;
use Phabalicious\Method\LocalMethod;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\MysqlMethod;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\Utilities\Utilities;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ShellCommandOptionsTest extends PhabTestCase
{

    const PORT = 12311;

    protected $application;

    protected $configuration;

    protected $shell;

    public function setup()
    {

        $this->application = new Application();
        $this->application->setVersion(Utilities::FALLBACK_VERSION);
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

        $this->configuration = new ConfigurationService($this->application, $logger);
        $method_factory = new MethodFactory($this->configuration, $logger);
        $method_factory->addMethod(new MysqlMethod($logger));
        $method_factory->addMethod(new DrushMethod($logger));
        $method_factory->addMethod(new ScriptMethod($logger));
        $method_factory->addMethod(new LocalMethod($logger));

        $this->application->add(new DrushCommand($this->configuration, $method_factory));

        $this->configuration->readConfiguration(__DIR__ . '/assets/shell-command-options-tests/fabfile.yaml');

        $this->shell = new LocalShellProvider($logger);
        $this->shell->setHostConfig($this->configuration->getHostConfig('local-shell'));
    }


    /**
     * @group shell-provider
     */
    public function testLocalShell()
    {
        $this->runDrush('local-shell', false);
    }

    /**
     * @group shell-provider
     * @group docker
     */
    public function testSshShell()
    {
        $this->startDocker();
        $this->runDrush('ssh-shell', true);
        $this->stopRunningDocker();
    }

    /**
     * @group shell-provider
     * @group docker
     */
    public function testDockerExecShell()
    {
        $this->startDocker();
        $this->runDrush('docker-exec-shell', false);
        $this->stopRunningDocker();
    }

    /**
     * This test needs a proper ssh key forwarding configured. when executed from within phpstorm make sure that
     * the environment variable SSH_AUTH_SOCK is set. If you are not stephan, change the username in the fabfile.
     * The test assumes that ssh to localhost, port 22 works w/o password-prompt.
     *
     * @group shell-provider
     * @group docker
     */
    public function testDockerExecOverSshShell()
    {
        $this->startDocker();
        $this->runDrush('docker-exec-over-ssh-shell', true);
        $this->stopRunningDocker();
    }

    /**
     * @group shell-provider
     * @group docker
     *
     * @param $config
     */
    protected function runDrush($config, $override_shell_provider_options): void
    {
        $command = $this->application->find('drush');
        $command_tester = new CommandTester($command);

        $args = [
            '-c' => $config,
            'command' => 'drush',
            'command-arguments' => ['version'],
        ];
        if ($override_shell_provider_options) {
            $filepath =__DIR__ . '/assets/shell-command-options-tests/testruns';
            $args['--set'] = sprintf('host.shellProviderOptions.1=%s', $filepath);
        }

        $command_tester->execute($args);

        $output = $command_tester->getDisplay();
        $this->assertStringContainsStringIgnoringCase("Drush version", $output);
    }

    private function startDocker()
    {
        $this->stopRunningDocker();
        $this->shell->cd(__DIR__ . '/..');
        $this->shell->run(
            sprintf(
                "docker run -d -p %d:22 --name test-shell-command-options factorial/drupal-docker:php-73",
                self::PORT
            )
        );

        $this->shell->run(
            'docker exec test-shell-command-options /bin/bash -c "mkdir /root/.ssh && chmod 700 /root/.ssh"'
        );
        $this->shell->run('chmod 600 tests/assets/shell-command-options-tests/testruns');
        $this->shell->run(
            'docker cp tests/assets/shell-command-options-tests/testruns.pub ' .
            'test-shell-command-options:/root/.ssh/authorized_keys'
        );
        $this->shell->run(
            'docker exec test-shell-command-options /bin/bash -c "chmod 600 /root/.ssh/authorized_keys ' .
            '&& chown root:root /root/.ssh/authorized_keys"'
        );
        sleep(5);
    }

    private function stopRunningDocker(): void
    {
        $this->shell->run("docker stop test-shell-command-options > /dev/null 2>&1 || true");
        $this->shell->run("docker rm test-shell-command-options > /dev/null 2>&1 || true");
    }
}
