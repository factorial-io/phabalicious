<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class ConfirmCallback implements CallbackInterface
{
    public static function getName(): string
    {
        return 'confirm';
    }

    public static function requires(): string
    {
        return '3.5';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->confirm($context, $arguments[0]);
    }

    public function confirm(TaskContextInterface $context, $message)
    {
        if (!$context->io()->confirm($message, false)) {
            throw new \RuntimeException('Script cancelled by user');
        }
    }
}
