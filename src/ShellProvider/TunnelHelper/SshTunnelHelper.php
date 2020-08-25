<?php


namespace Phabalicious\ShellProvider\TunnelHelper;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Method\TaskContextInterface;

class SshTunnelHelper extends TunnelHelperBase implements HostToHostTunnelInterface, LocalToHostTunnelInterface
{

    public function createLocalToHostTunnel(
        HostConfig $config,
        TaskContextInterface $context,
        TunnelDataInterface $tunnel_data = null
    ): TunnelDataInterface {
        return $this->createTunnel($config, $config, false, $context, $tunnel_data);
    }

    public function createHostToHostTunnel(
        HostConfig $source_config,
        HostConfig $target_config,
        TaskContextInterface $context,
        TunnelDataInterface $tunnel_data = null
    ): TunnelDataInterface {
        return $this->createTunnel($source_config, $target_config, true, $context, $tunnel_data);
    }

    /**
     * @param HostConfig $source_config
     * @param HostConfig $target_config
     * @param bool $remote
     * @param TaskContextInterface $context
     * @param TunnelDataInterface|null $tunnel_data
     * @return TunnelDataInterface
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    private function createTunnel(
        HostConfig $source_config,
        HostConfig $target_config,
        bool $remote,
        TaskContextInterface $context,
        TunnelDataInterface $tunnel_data = null
    ) {
        $key = $source_config['configName'] . '->' . $target_config['configName'];
        if ($remote) {
            $key .= '--remote';
        }
        if (!$tunnel_data) {
            $tunnel_data = new TunnelData($key, TunnelDataInterface::CREATING_STATE);
        }
        $tunnel_data->setState(TunnelDataInterface::CREATING_STATE);

        if (empty($target_config['sshTunnel']['destHost'])) {
            $this->logger->notice('Getting ip for config `' . $target_config['configName'] . '`...');
            $ctx = clone $context;
            $context->getConfigurationService()->getMethodFactory()->runTask('getIp', $target_config, $ctx);
            $tunnel = $target_config['sshTunnel'];
            if ($ip = $ctx->getResult('ip', false)) {
                $tunnel['destHost'] = $ctx->getResult('ip');
                $target_config['sshTunnel'] = $tunnel;
            } else {
                $this->logger->warning(sprintf('Could not get ip for config `%s`!', $target_config['configName']));
                $tunnel_data->setState(TunnelDataInterface::FAILED_STATE);
                return $tunnel_data;
            }
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

        $tunnel_data->setState(TunnelDataInterface::CREATED_STATE);
        $tunnel_data->setProcess($process);

        return $tunnel_data;
    }

    public static function isConfigSupported(HostConfig $config)
    {
        return !empty($config['sshTunnel']);
    }
}
