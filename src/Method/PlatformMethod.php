<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Psr\Log\LoggerInterface;

class PlatformMethod extends BaseMethod
{
    private $drushMethod;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->drushMethod = new DrushMethod($logger);
    }

    public function getName(): string
    {
        return 'platform';
    }

    public function getOverriddenMethod()
    {
        return 'drush';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == $this->getName();
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result = parent::getDefaultConfig($configuration_service, $host_config);
        $result['executables']['platform'] = '~/.platformsh/bin/platform';
        $result['platformRemote'] = 'platform';


        return $result;
    }

    protected function runCommand(HostConfig $host_config, TaskContextInterface $context, string $command)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);
        $executable = $shell->expandCommand('#!platform');

        return $shell->runProcess(
            [
                $executable,
                $command,
            ],
            $context,
            true,
            true
        );
    }

    public function platform(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command', false);
        if (!$command) {
            throw new \InvalidArgumentException('Missing command for platform-command!');
        }
        if (!$this->runCommand($host_config, $context, $command)) {
            $context->setResult('exitCode', 1);
        }
    }

    public function drush(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->drushMethod->drush($host_config, $context);
    }

    public function deploy(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);
        $result = $shell->runProcess(
            [
                'git',
                'push',
                $host_config['platformRemote'],
                $host_config['branch']
            ],
            $context,
            true,
            true
        );

        if (!$result) {
            throw new \RuntimeException('Could not push code to platforms git-repository');
        }

        $context->getOutput()->writeln('Wait 10 seconds for remote instance...');
        sleep(10);
    }

}
