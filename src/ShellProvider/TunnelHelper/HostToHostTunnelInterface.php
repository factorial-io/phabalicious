<?php

namespace Phabalicious\ShellProvider\TunnelHelper;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;

interface HostToHostTunnelInterface
{
    public function createHostToHostTunnel(
        HostConfig $source_config,
        HostConfig $dest_config,
        TaskContextInterface $context,
        ?TunnelDataInterface $tunnel_data = null,
    ): TunnelDataInterface;
}
