<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\AppDefaultStages;

abstract class AppBaseCommand extends BaseCommand
{
    /**
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function executeStages(array $stages, string $command, TaskContextInterface $context, string $message)
    {
        AppDefaultStages::executeStages(
            $this->getMethods(),
            $this->getHostConfig(),
            $stages,
            $command,
            $context,
            $message
        );
    }
}
