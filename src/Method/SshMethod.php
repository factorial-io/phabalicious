<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class SshMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'ssh';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'ssh';
    }
}