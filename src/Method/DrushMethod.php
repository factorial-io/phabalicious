<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;

class DrushMethod extends BaseMethod implements MethodInterface {

    public function getName(): string
    {
        return 'drush';
    }

    public function supports(string $method_name): bool
    {
        return (in_array($method_name, ['drush', 'drush7', 'drush8', 'drush9']));
    }


}