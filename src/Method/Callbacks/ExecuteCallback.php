<?php

namespace Phabalicious\Method\Callbacks;

use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;

class ExecuteCallback implements CallbackInterface
{
    protected $method;

    public static function getName(): string
    {
        return 'execute';
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
        $task_name = array_shift($args);
        $return_code = $this->method->executeCommand($context, $task_name, $args);

        if (0 !== $return_code && $this->method->getBreakOnFirstError()) {
            // The command returned a non zero value, lets stop here.
            throw new \RuntimeException(sprintf('Execute callback returned a non-zero return-code: %d', $return_code));
        }
    }
}
