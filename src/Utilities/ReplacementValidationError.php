<?php

namespace Phabalicious\Utilities;

use Symfony\Component\Yaml\Yaml;

class ReplacementValidationError
{

    /**
     * @var array
     */
    protected $context;

    protected $lineNumber;

    /**
     * @var string[]
     */
    protected $failedPattern;

    /**
     * @param array $context
     * @param $line_number
     * @param string $failed_pattern
     */
    public function __construct(array $context, $line_number, string $failed_pattern)
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
     * @return mixed
     */
    public function getLineNumber()
    {
        return $this->lineNumber;
    }

    /**
     * @return array|string[]
     */
    public function getFailedPattern()
    {
        return $this->failedPattern;
    }

    public function getFailedLineWithinContext(): string
    {
        $return = [];
        foreach ($this->context as $ndx => $line) {
            print_r($line);
            $return[] = (($ndx == $this->lineNumber) ? ">  " : "   ") . $ndx . ": " . Yaml::dump($line, 0);
        }
        return implode("\n", $return);
    }

    public function getFailedLine()
    {
        return $this->context[$this->getLineNumber()];
    }
}
