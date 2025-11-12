<?php

namespace Phabalicious\Tests;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;

class DebugCallback implements CallbackInterface
{
    public $debugOutput = [];

    protected $captureAllArguments = false;

    public static function getName(): string
    {
        return 'debug';
    }

    public static function requires(): string
    {
        return '3.6';
    }

    public function __construct($captureAllArguments)
    {
        $this->captureAllArguments = $captureAllArguments;
    }

    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $message = $this->captureAllArguments ? $arguments : array_shift($arguments);
        $this->debugOutput[] = $message;
    }
}
