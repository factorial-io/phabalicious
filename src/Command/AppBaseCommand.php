<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Method\TaskContextInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AppBaseCommand extends BaseCommand
{
    /**
     * @param array $stages
     * @param string $command
     * @param TaskContextInterface $context
     * @param string $message
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function executeStages(array $stages, string $command, TaskContextInterface $context, string $message)
    {
        foreach ($stages as $stage) {
            $context->getOutput()->writeln(sprintf('%s, stage %s', $message, $stage['stage']));
            $context->set('currentStage', $stage);
            $this->getMethods()->runTask($command, $this->getHostConfig(), $context);
        }
    }


}
