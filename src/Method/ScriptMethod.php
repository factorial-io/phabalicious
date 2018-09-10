<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class ScriptMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'script';
    }

    public function supports(string $method_name): bool
    {
        return $method_name = 'script';
    }


}