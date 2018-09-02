<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class SshMethod extends BaseMethod implements MethodInterface
{

    public static function getName(): string
    {
        return 'ssh';
    }

    public function supports(string $method_name): bool
    {
        return (in_array($method_name, ['ssh']));
    }
}