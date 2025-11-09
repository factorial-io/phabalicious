<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class LogMessageCallback implements CallbackInterface
{
    public static function getName(): string
    {
        return 'log_message';
    }

    public static function requires(): string
    {
        return '3.4';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->logMessage($context, $arguments[0], $arguments[1] ?? '');
    }

    public function logMessage(TaskContextInterface $context, $log_level, $log_message)
    {
        if (empty($log_message)) {
            $log_message = $log_level;
            $log_level = 'info';
        }
        $log_level = strtolower($log_level);
        if ('success' == $log_level) {
            $context->io()->success($log_message);
        } elseif ('warning' == $log_level) {
            $context->io()->warning($log_message);
        } elseif ('error' == $log_level) {
            $context->io()->warning($log_message);
        } else {
            $context->io()->note($log_message);
        }
    }
}
