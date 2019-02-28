<?php
namespace Phabalicious\Utilities;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContextInterface;

class AppDefaultStages
{
    const CREATE = [
        [
            'stage' => 'prepareDestination',
        ],
        [
            'stage' => 'installCode',
        ],
        [
            'stage' => 'spinUp',
        ],
        [
            'stage' => 'installDependencies',
        ],
        [
            'stage' => 'install',
        ],
    ];

    const DEPLOY = [
        [
            'stage' => 'spinUp',
        ]
    ];

    const DESTROY = [
        [
            'stage' => 'spinDown',
        ],
        [
            'stage' => 'deleteContainer',
        ],
    ];

    const CREATE_CODE = [
        [
            'stage' => 'installCode',
        ],
        [
            'stage' => 'installDependencies',
        ],
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
            $context->getOutput()->writeln(sprintf('%s, stage %s', $message, $stage['stage']));
            $context->set('currentStage', $stage);
            $method_factory->runTask($command, $host_config, $context);
        }
    }
}
