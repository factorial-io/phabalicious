<?php

namespace Phabalicious\Method\Callbacks;

use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;

class BreakOnFirstError implements CallbackInterface
{
    protected $method;

    public static function getName(): string
    {
        return 'break_on_first_error';
    }

    public static function requires(): string
    {
        return '3.6';
    }

    public function __construct(ScriptMethod $method)
    {
        $this->method = $method;
    }

    public function handle(TaskContextInterface $context, ...$args)
    {
        $flag = array_shift($args);
        $context->set('break_on_first_error', $flag);
        $this->method->setBreakOnFirstError($flag);
    }
}
