<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class DrupalconsoleMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'drupal';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'drupalconsole' || $method_name === 'drupal';
    }

    public function getGlobalSettings(): array
    {
        return [
            'executables' => [
                'drupal' => 'drupal',
            ],
        ];
    }
}