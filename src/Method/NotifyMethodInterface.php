<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;

interface NotifyMethodInterface
{
    public function sendNotification(
        HostConfig $host_config,
        string $message,
        TaskContextInterface $context,
        string $type,
        array $meta,
    );

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context);
}
