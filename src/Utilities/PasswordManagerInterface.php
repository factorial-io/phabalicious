<?php

namespace Phabalicious\Utilities;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;

interface PasswordManagerInterface
{
    public function getContext(): TaskContextInterface;

    public function setContext(TaskContextInterface $context): PasswordManagerInterface;

    public function getKeyFromLogin($host, $port, $user);

    public function getPasswordFor(string $key);

    public function resolveSecrets($data);

    public function encrypt($data, $secret_name);

    public function decrypt($data, $secret_name);

    public function setSecret($secret_name, $value);

}
