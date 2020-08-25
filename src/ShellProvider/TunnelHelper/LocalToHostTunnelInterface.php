<?php


namespace Phabalicious\ShellProvider\TunnelHelper;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;

interface LocalToHostTunnelInterface
{
    public function createLocalToHostTunnel(
        HostConfig $config,
        TaskContextInterface $context,
        TunnelDataInterface $tunnel_data = null
    ): TunnelDataInterface;
}
