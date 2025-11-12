<?php

namespace Phabalicious\ShellProvider;

class ShellOptions
{
    protected bool $useTty = false;
    protected bool $quiet = true;
    protected bool $shellExecutableProvided = false;

    public function useTty(): bool
    {
        return $this->useTty;
    }

    public function setUseTty(bool $useTty): ShellOptions
    {
        $this->useTty = $useTty;

        return $this;
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function setQuiet(bool $quiet): ShellOptions
    {
        $this->quiet = $quiet;

        return $this;
    }

    public function isShellExecutableProvided(): bool
    {
        return $this->shellExecutableProvided;
    }

    public function setShellExecutableProvided($shellExecutableProvided): ShellOptions
    {
        $this->shellExecutableProvided = $shellExecutableProvided;

        return $this;
    }
}
