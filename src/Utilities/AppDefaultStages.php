<?php
namespace Phabalicious\Utilities;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContextInterface;

class AppDefaultStages
{
    const CREATE = [
        'prepareDestination',
        'installCode',
        'spinUp',
        'checkConnectivity',
        'installDependencies',
        'install',
    ];

    const DEPLOY = [
        'spinUp',
    ];

    const DESTROY = [
        'spinDown',
        'deleteContainer',
    ];



    /**
     * @param MethodFactory $method_factory
     * @param HostConfig $host_config
     * @param array $stages
     * @param string $command
     * @param TaskContextInterface $context
     * @param string $message
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    public static function executeStages(
        MethodFactory $method_factory,
        HostConfig $host_config,
        array $stages,
        string $command,
        TaskContextInterface $context,
        string $message
    ) {
        foreach ($stages as $stage) {
            $context->io()->comment(sprintf('%s, stage %s', $message, $stage));
            $context->set('currentStage', $stage);
            $method_factory->runTask($command, $host_config, $context);
        }
    }
}
