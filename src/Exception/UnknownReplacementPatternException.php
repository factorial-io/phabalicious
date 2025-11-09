<?php

namespace Phabalicious\Exception;

use Phabalicious\Utilities\ReplacementValidationError;

class UnknownReplacementPatternException extends \Exception
{
    private $patterns;
    private $error;

    /**
     * ValidationFailedException constructor.
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
                "Missing command-line-argument `%s`!\n\n".
                'Please add `--arguments %s=<YOUR-VALUE>` to the invocation of the command',
                $argument,
                $argument
            );
        }

        return implode("\n", [
            sprintf('Unknown pattern `%s` in', $this->error->getFailedPattern()),
            '',
            $this->error->getFailedLineWithinContext(),
        ]);
    }

    public function getOffendingLine(): string
    {
        return $this->error->getLineNumber();
    }

    public function getError(): ReplacementValidationError
    {
        return $this->error;
    }
}
