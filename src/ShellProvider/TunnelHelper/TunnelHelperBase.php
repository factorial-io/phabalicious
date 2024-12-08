<?php

namespace Phabalicious\ShellProvider\TunnelHelper;

use Psr\Log\LoggerInterface;

abstract class TunnelHelperBase implements TunnelHelperInterface
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
