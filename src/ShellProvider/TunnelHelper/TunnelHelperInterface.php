<?php


namespace Phabalicious\ShellProvider\TunnelHelper;

use Phabalicious\Configuration\HostConfig;

interface TunnelHelperInterface
{
    public static function isConfigSupported(HostConfig $config);
}
