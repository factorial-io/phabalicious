<?php

namespace Phabalicious\CustomPlugin;

use Phabalicious\Method\BaseMethod;
use Phabalicious\Method\MethodInterface;

class CustomMethod extends BaseMethod implements MethodInterface
{
    public function getName(): string
    {
        return 'custom';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == $this->getName();
    }
}
