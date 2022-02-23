<?php

namespace Phabalicious\Command;

interface DatabaseSubCommandInterface
{
    public function getSubcommandInfo(): array;

    public function getSubcommandArguments(): array;
}
