<?php

namespace Phabalicious\ShellProvider\TunnelHelper;

use Symfony\Component\Process\Process;

class TunnelData implements TunnelDataInterface
{
    protected $name;
    protected $state;
    protected $process;

    public function __construct($name, $state)
    {
        $this
            ->setName($name)
            ->setState($state);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name): TunnelData
    {
        $this->name = $name;

        return $this;
    }

    public function setState(string $state): TunnelData
    {
        $this->state = $state;

        return $this;
    }

    public function getState(): mixed
    {
        return $this->state;
    }

    public function setProcess(Process $process): TunnelData
    {
        $this->process = $process;

        return $this;
    }
}
