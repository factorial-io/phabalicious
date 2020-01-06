<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface NotifyMethodInterface
{

    public function sendNotification(
        HostConfig $host_config,
        string $message,
        TaskContextInterface $context,
        string $type,
        array $meta
    );

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context);
}
