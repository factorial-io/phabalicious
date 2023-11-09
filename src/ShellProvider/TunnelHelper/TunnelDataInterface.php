<?php


namespace Phabalicious\ShellProvider\TunnelHelper;

use Symfony\Component\Process\Process;

interface TunnelDataInterface
{
    const CREATING_STATE = 'creating';
    const CREATED_STATE = 'created';
    const FAILED_STATE = 'failed';

    /**
     * @return string
     */
    public function getName(): string;


    /**
     * @return mixed
     */
    public function getState(): mixed;

    public function setState(string $state);

    public function setProcess(Process $process);
}
