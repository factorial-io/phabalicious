<?php

namespace Phabalicious\ShellProvider\TunnelHelper;

use Symfony\Component\Process\Process;

interface TunnelDataInterface
{
    public const CREATING_STATE = 'creating';
    public const CREATED_STATE = 'created';
    public const FAILED_STATE = 'failed';

    public function getName(): string;

    public function getState(): mixed;

    public function setState(string $state);

    public function setProcess(Process $process);
}
