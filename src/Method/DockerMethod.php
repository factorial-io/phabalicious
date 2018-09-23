<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class DockerMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'docker';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'docker';
    }
}