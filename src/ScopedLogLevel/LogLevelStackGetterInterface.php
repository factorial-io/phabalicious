<?php

namespace Phabalicious\ScopedLogLevel;

interface LogLevelStackGetterInterface
{
    public function getLogLevelStack(): LoglevelStackInterface;
    public function getErrorLogLevelStack(): LoglevelStackInterface;
}
