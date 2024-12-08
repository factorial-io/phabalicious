<?php


namespace Phabalicious\ShellProvider;

class ShellOptions
{
    protected bool $useTty = false;
    protected bool $quiet = true;
    protected bool $shellExecutableProvided = false;

    /**
     * @return bool
     */
    public function useTty(): bool
    {
        return $this->useTty;
    }

    /**
     * @param bool $useTty
     * @return ShellOptions
     */
    public function setUseTty(bool $useTty): ShellOptions
    {
        $this->useTty = $useTty;
        return $this;
    }

    /**
     * @return bool
     */
    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    /**
     * @param bool $quiet
     * @return ShellOptions
     */
    public function setQuiet(bool $quiet): ShellOptions
    {
        $this->quiet = $quiet;
        return $this;
    }

    public function isShellExecutableProvided(): bool
    {
        return $this->shellExecutableProvided;
    }

    /**
     * @param mixed $shellExecutableProvided
     * @return ShellOptions
     */
    public function setShellExecutableProvided($shellExecutableProvided): ShellOptions
    {
        $this->shellExecutableProvided = $shellExecutableProvided;
        return $this;
    }
}
