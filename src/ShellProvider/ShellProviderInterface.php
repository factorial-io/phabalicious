<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Validation\ValidationErrorBagInterface;

interface ShellProviderInterface
{
    public function getName(): string;

    public function validateConfig(array $config, ValidationErrorBagInterface $errors);

}