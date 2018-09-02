<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class FilesMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'files';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'files';
    }
}