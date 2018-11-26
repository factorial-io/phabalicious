<?php

namespace Phabalicious\ScopedLogLevel;

class ScopedLogLevel
{
    private $stack;

    public function __construct(LogLevelStackGetterInterface $decorated, string $new_log_level)
    {
        $this->stack = $decorated->getLogLevelStack();
        $this->stack->pushLoglevel($new_log_level);
    }

    public function __destruct()
    {
        $this->stack->popLogLevel();
    }
}
