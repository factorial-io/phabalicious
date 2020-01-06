<?php

namespace Phabalicious\ScopedLogLevel;

interface LoglevelStackInterface
{
    public function pushLoglevel(string $log_level);
    public function popLogLevel();
    public function get(): string;
}
