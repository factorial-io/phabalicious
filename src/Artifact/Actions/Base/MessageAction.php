<?php

namespace Phabalicious\Artifact\Actions\Base;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;

class MessageAction extends ActionBase
{
    const MESSAGE_TYPE_MAPPING = [
        'error' => 'error',
        'warning' => 'warning',
        'note' => 'note',
        'comment' => 'comment',
        'success' => 'success'
    ];

    public function __construct()
    {
    }

    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
        $validation->hasKey('message', 'Log action needs a message');
        if (!empty($validation->getConfig()['type'])) {
            $validation->isOneOf(
                'type',
                array_keys(self::MESSAGE_TYPE_MAPPING)
            );
        }
    }

    protected function runImplementation(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $install_dir,
        string $target_dir
    ) {
        $severity = $this->getArguments()['type'] ?? 'note';
        $message = $this->getArguments()['message'];

        $fn = self::MESSAGE_TYPE_MAPPING[$severity];
        $context->io()->{$fn}($message);
    }
}
