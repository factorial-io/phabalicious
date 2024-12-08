<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Exception\FailedShellCommandException;

class CommandResult
{
    private $exitCode;
    private $lines;

    /**
     * CommandResult constructor.
     */
    public function __construct(int $exit_code, array $lines)
    {
        $this->exitCode = $exit_code;
        $this->lines = $lines;
    }

    public function succeeded(): bool
    {
        return 0 == $this->exitCode;
    }

    public function failed(): bool
    {
        return !$this->succeeded();
    }

    public function getOutput(): array
    {
        return $this->lines;
    }

    public function getTrimmedOutput(): string
    {
        return trim(implode("\n", $this->lines));
    }

    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * @throws FailedShellCommandException
     */
    public function throwException(string $message)
    {
        throw new FailedShellCommandException($message."\n".implode("\n", $this->getOutput()));
    }
}
