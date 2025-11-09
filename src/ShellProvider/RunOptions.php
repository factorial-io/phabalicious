<?php

namespace Phabalicious\ShellProvider;

enum RunOptions
{
    case NONE;
    case CAPTURE_OUTPUT;
    case HIDE_OUTPUT;
    case CAPTURE_AND_HIDE_OUTPUT;

    public function isCapturingOutput(): bool
    {
        return self::CAPTURE_OUTPUT === $this || self::CAPTURE_AND_HIDE_OUTPUT === $this;
    }

    public function hideOutput(): bool
    {
        return self::HIDE_OUTPUT === $this || self::CAPTURE_AND_HIDE_OUTPUT === $this;
    }
}
