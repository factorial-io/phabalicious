<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;

class AssertContainsCallback implements CallbackInterface
{
    public static function getName(): string
    {
        return 'assert_contains';
    }

    public static function requires(): string
    {
        return '3.5.10';
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        if (count($arguments) < 3) {
            throw new \RuntimeException('Not enough arguments passed to '.self::getName());
        }
        $this->assertContains($context, $arguments[0], $arguments[1], $arguments[2]);
    }

    public function assertContains(
        TaskContextInterface $context,
        $needle,
        $haystack,
        $error_message,
    ) {
        if (false === strpos($haystack, $needle)) {
            throw new \RuntimeException($error_message);
        }
    }
}
