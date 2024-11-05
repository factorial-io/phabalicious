<?php

namespace Phabalicious\Exception;

use Phabalicious\Utilities\ReplacementValidationError;

class UnknownReplacementPatternException extends \Exception
{
    private $patterns;
    private $error;

    /**
     * ValidationFailedException constructor.
     *
     * @param \Phabalicious\Utilities\ReplacementValidationError $error
     * @param array $patterns
     */
    public function __construct(ReplacementValidationError $error, array $patterns)
    {
        $this->error = $error;
        $this->patterns = $patterns;
        parent::__construct();
        $this->message = $this->__toString();
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    public function __toString()
    {
        if ($argument = $this->error->getMissingArgument()) {
            return sprintf(
                "Missing command-line-argument `%s`!\n\n" .
                "Please add `--arguments %s=<YOUR-VALUE>` to the invocation of the command",
                $argument,
                $argument
            );
        }
        return implode("\n", [
            sprintf("Unknown pattern `%s` in", $this->error->getFailedPattern()),
            "",
            $this->error->getFailedLineWithinContext(),
        ]);
    }

    /**
     * @return string
     */
    public function getOffendingLine(): string
    {
        return $this->error->getLineNumber();
    }

    /**
     * @return \Phabalicious\Utilities\ReplacementValidationError
     */
    public function getError(): \Phabalicious\Utilities\ReplacementValidationError
    {
        return $this->error;
    }
}
