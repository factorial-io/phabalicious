<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class AssertNonZeroCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'assert_nonzero';
    }

    /**
     * @inheritDoc
     */
    public static function requires()
    {
        return '3.5.10';
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->assertZero($context, $arguments[0], $arguments[1]);
    }

    public function assertZero(
        TaskContextInterface $context,
        $value,
        $error_message
    ) {

        if (empty($value)) {
            throw new \RuntimeException($error_message);
        }
    }
}
