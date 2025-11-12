<?php

namespace Phabalicious\Artifact\Actions\Base;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationService;

class LogAction extends ActionBase
{
    public const SEVERITY_MAPPINGS = [
        'error' => 'error',
        'warning' => 'warning',
        'notice' => 'notice',
        'info' => 'info',
        'debug' => 'debug',
    ];

    public function __construct()
    {
    }

    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
        $validation->hasKey('message', 'Log action needs a message');
        if (!empty($validation->getConfig()['severity'])) {
            $validation->isOneOf(
                'severity',
                array_keys(self::SEVERITY_MAPPINGS)
            );
        }
    }

    protected function runImplementation(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $install_dir,
        string $target_dir,
    ) {
        $severity = $this->getArguments()['severity'] ?? 'notice';
        $message = $this->getArguments()['message'];

        $fn = self::SEVERITY_MAPPINGS[$severity];
        $context->getConfigurationService()->getLogger()->{$fn}($message);
    }
}
