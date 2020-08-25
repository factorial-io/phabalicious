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

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return TunnelData
     */
    public function setName($name): TunnelData
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param mixed $state
     * @return TunnelData
     */
    public function setState(string $state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    public function setProcess(Process $process): TunnelData
    {
        $this->process = $process;
        return $this;
    }
}
