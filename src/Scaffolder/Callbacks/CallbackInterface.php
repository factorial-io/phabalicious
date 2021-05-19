<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\PluginInterface;

interface CallbackInterface extends PluginInterface
{

    /**
     * transforms a bunch of files to another bunch of files.
     *
     * @param TaskContextInterface $context
     *   The current context.
     * @param $arguments
     *   Parsed arguments.
     */
    public function handle(TaskContextInterface $context, ...$arguments);
}
