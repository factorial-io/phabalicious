<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\MethodFactory;

class ComposerCommand extends SimpleExecutableInvocationCommand
{
    public function __construct(ConfigurationService $configuration, MethodFactory $method_factory)
    {
        parent::__construct($configuration, $method_factory, 'composer');
    }
}
