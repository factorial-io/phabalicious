<?php

namespace Phabalicious\Artifact\Actions;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface ActionInterface
{
    public function validateConfig($host_config, array $action_config, ValidationErrorBagInterface $errors);

    public function setArguments($arguments);

    public function run(HostConfig $host_config, TaskContextInterface $context);
}
