<?php

namespace Phabalicious\Utilities;

use Symfony\Component\Yaml\Yaml;

class ReplacementValidationError
{

    /**
     * @var array
     */
    protected array $context;

    protected int $lineNumber;

    protected string $failedPattern;

    /**
     * @param array $context
     * @param int $line_number
     * @param string $failed_pattern
     */
    public function __construct(array $context, int $line_number, string $failed_pattern)
    {
        $this->context = $context;
        $this->lineNumber = $line_number;
        $this->failedPattern = $failed_pattern;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return int
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @return string
     */
    public function getFailedPattern(): string
    {
        return $this->failedPattern;
    }

    public function getFailedLineWithinContext(): string
    {
        $return = [];
        foreach ($this->context as $ndx => $line) {
            $return[] = (($ndx === $this->lineNumber) ? ">  " : "   ") . $ndx . ": " . Yaml::dump($line, 0);
        }
        return implode("\n", $return);
    }

    public function getFailedLine(): string
    {
        return $this->context[$this->getLineNumber()];
    }

    public function getMissingArgument(): bool|string
    {
        $failedLine = $this->getFailedLine();
        $p = strpos($failedLine, "%arguments.");
        if ($p === false) {
            return false;
        }
        $p += 11;
        $endp = strpos($failedLine, '%', $p);
        return substr($failedLine, $p, $endp - $p);
    }
}
