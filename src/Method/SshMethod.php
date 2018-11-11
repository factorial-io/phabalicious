<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Validation\ValidationErrorBagInterface;

class SshMethod extends BaseMethod implements MethodInterface
{

    protected $creatingTunnel = false;
    protected $tunnels = [];

    public function getName(): string
    {
        return 'ssh';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'ssh';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        // Implementation found in SShSellProvider.
        return [
            'shellProvider' => SshShellProvider::PROVIDER_NAME,
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        // Implementation found in SShSellProvider.
    }

    public function createShellProvider(array $host_config)
    {
        return ShellProviderFactory::create(SshShellProvider::PROVIDER_NAME, $this->logger);
    }


    /**
     * @param HostConfig $config
     * @param TaskContextInterface $context
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    private function createLocalToHostTunnel(HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->notice('Creating ssh-tunnel from local to `' . $config['configName'] . '` ...');
        $this->createTunnel($config, $config, false, $context);
    }

    /**
     * @param HostConfig $config
     * @param HostConfig $source_config
     * @param TaskContextInterface $context
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    private function createRemoteToHostTunnel(
        HostConfig $config,
        HostConfig $source_config,
        TaskContextInterface $context
    ) {
        $this->logger->notice(
            'Creating ssh-tunnel from `' .
            $config['configName'] .
            '` to `' .
            $source_config['configName'] . '` ...'
        );
        $this->createTunnel($config, $source_config, true, $context);
    }

    /**
     * @param HostConfig $source_config
     * @param HostConfig $target_config
     * @param bool $remote
     * @param TaskContextInterface $context
     * @return mixed
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    private function createTunnel(
        HostConfig $source_config,
        HostConfig $target_config,
        bool $remote,
        TaskContextInterface $context
    ) {
        $key = $source_config['configName'] . '->' . $target_config['configName'];
        if ($remote) {
            $key .= '--remote';
        }
        if (!empty($this->tunnels[$key]['creating']) || !empty($this->tunnels[$key]['created'])) {
            return $this->tunnels[$key]['process'];
        }
        if (empty($this->tunnels[$key])) {
            $this->tunnels[$key] = [
                'creating' => false,
                'created' => false,
                'process' => null,
            ];
        }
        $this->tunnels[$key]['creating'] = true;

        if (empty($target_config['destHost'])) {
            $this->logger->notice('Getting ip for config `' . $target_config['configName'] . '`...');
            $ctx = clone $context;
            $context->getConfigurationService()->getMethodFactory()->runTask('getIp', $target_config, $ctx);
            $tunnel = $target_config['sshTunnel'];
            $tunnel['destHost'] = $ctx->getResult('ip');
            $target_config['sshTunnel'] = $tunnel;
        }

        $prefix = [];
        if ($remote) {
            $prefix = [
                'ssh',
                '-p',
                $source_config['port'],
                $source_config['user'] . '@' . $source_config['host'],
                '-A'
            ];
        }

        $process = $source_config->shell()->createTunnelProcess($target_config, $prefix);


        $this->tunnels[$key]['creating'] = false;
        $this->tunnels[$key]['created'] = $process != null;

        return $process;
    }

    /**
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        if ($this->creatingTunnel) {
            return;
        }
        $this->creatingTunnel = true;
        if (!in_array($task, ['about', 'doctor', 'sshCommand'])) {
            if (!empty($config['sshTunnel'])) {
                $this->createLocalToHostTunnel($config, $context);
            }
        }
        if (in_array($task, ['copyFrom'])) {
            $from_config = $context->get('from', false);
            if ($from_config && !empty($from_config['sshTunnel'])) {
                $this->createLocalToHostTunnel($from_config, $context);
                $this->createRemoteToHostTunnel($config, $from_config, $context);
            }
        }
        $this->creatingTunnel = false;
    }

}