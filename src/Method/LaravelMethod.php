<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;

class LaravelMethod extends RunCommandBaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'laravel';
    }

    public function getExecutableName(): string
    {
        return "php artisan";
    }

    public function artisan(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');
        $this->runCommand($host_config, $context, $command);
    }

    public function install(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runCommand($host_config, $context, "db:wipe");
        $this->runCommand($host_config, $context, "migrate");
        $this->runCommand($host_config, $context, "db:seed");
    }

    public function reset(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runCommand($host_config, $context, "migrate");
        $this->runCommand($host_config, $context, "cache:clear");
    }
}
