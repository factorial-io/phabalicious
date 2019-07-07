<?php

namespace Phabalicious\Exception;

class UnknownReplacementPatternException extends \Exception
{
    private $patterns;
    private $offendingLine;

    /**
     * ValidationFailedException constructor.
     *
     * @param string $offending_line
     * @param array $patterns
     */
    public function __construct(string $offending_line, array $patterns)
    {
        $this->offendingLine = $offending_line;
        $this->patterns = $patterns;
        parent::__construct();
    }

    public function getPatterns()
    {
        return $this->patterns;
    }

    public function __toString()
    {
        return implode("\n", [
            'Error in ' . $this->getOffendingLine(),
            "Available patterns:",
            implode("\n", $this->getPatterns())
        ]);
    }

    /**
     * @return string
     */
    public function getOffendingLine(): string
    {
        return $this->offendingLine;
    }
}
