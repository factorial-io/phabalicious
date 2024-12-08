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
        return $this === self::CAPTURE_OUTPUT || $this === self::CAPTURE_AND_HIDE_OUTPUT;
    }

    public function hideOutput(): bool
    {
        return $this === self::HIDE_OUTPUT || $this === self::CAPTURE_AND_HIDE_OUTPUT;
    }
}
