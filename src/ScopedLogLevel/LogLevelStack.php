<?php

namespace Phabalicious\ScopedLogLevel;

class LogLevelStack implements LoglevelStackInterface
{
    private $stack = [];
    private $defaultLogLevel;

    public function __construct($default_log_level)
    {
        $this->defaultLogLevel = $default_log_level;
        $this->pushLoglevel($default_log_level);
    }

    public function pushLoglevel(string $log_level)
    {
        $this->stack[] = $log_level;
    }

    public function popLogLevel()
    {
        array_pop($this->stack);
    }

    public function get(): string
    {
        return count($this->stack) > 0 ? end($this->stack) : $this->defaultLogLevel;
    }

    public function __toString(): string
    {
        return $this->get();
    }
}
