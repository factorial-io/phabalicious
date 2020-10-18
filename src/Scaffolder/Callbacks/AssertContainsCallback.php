<?php

namespace Phabalicious\Scaffolder\Callbacks;

use http\Exception\InvalidArgumentException;
use Phabalicious\Method\TaskContextInterface;

class AssertContainsCallback implements CallbackInterface
{
    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'assert_contains';
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
        if (count($arguments) < 3) {
            throw new \RuntimeException("Not enough arguments passed to " . self::getName());
        }
        $this->assertContains($context, $arguments[0], $arguments[1], $arguments[2]);
    }

    public function assertContains(
        TaskContextInterface $context,
        $needle,
        $haystack,
        $error_message
    ) {
        if (strpos($haystack, $needle) === false) {
            throw new \RuntimeException($error_message);
        }
    }
}
