<?php

namespace Phabalicious\Utilities;

interface PasswordManagerInterface
{
    public function getPasswordFor(string $host, int $port, string $user);
}
