<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class LogMessageCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'log_message';
    }

    /**
     * @inheritDoc
     */
    public static function requires(): string
    {
        return '3.4';
    }

    /**
     * @inheritDoc
     */
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
        if ($log_level == 'success') {
            $context->io()->success($log_message);
        } elseif ($log_level == 'warning') {
            $context->io()->warning($log_message);
        } elseif ($log_level == 'error') {
            $context->io()->warning($log_message);
        } else {
            $context->io()->note($log_message);
        }
    }
}
