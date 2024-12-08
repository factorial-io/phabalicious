<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class AssertNonZeroCallback implements CallbackInterface
{
    public static function getName(): string
    {
        return 'assert_nonzero';
    }

    public static function requires(): string
    {
        return '3.5.10';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->assertZero($context, $arguments[0], $arguments[1]);
    }

    public function assertZero(
        TaskContextInterface $context,
        $value,
        $error_message,
    ) {
        if (empty($value)) {
            throw new \RuntimeException($error_message);
        }
    }
}
