<?php

namespace Phabalicious\Method\Callbacks;

use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;

class FailOnMissingDirectory implements CallbackInterface
{
    protected $method;

    public static function getName(): string
    {
        return 'fail_on_missing_directory';
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
        $dir = array_shift($args);
        if (!$context->getShell()->exists($dir)) {
            throw new \Exception('`'.$dir.'` . does not exist!');
        }
    }
}
