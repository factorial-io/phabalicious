<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class GitMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'git';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'git';
    }
}