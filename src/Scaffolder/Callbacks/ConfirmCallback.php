<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;

class ConfirmCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'confirm';
    }

    /**
     * @inheritDoc
     */
    public static function requires()
    {
        return '3.5';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->confirm($context, $arguments[0]);
    }

    public function confirm(TaskContextInterface $context, $message)
    {
        if (!$context->io()->confirm($message)) {
            throw new \RuntimeException('Script cancelled by user');
        }
    }
}
