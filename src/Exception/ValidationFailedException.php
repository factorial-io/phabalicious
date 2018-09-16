<?php

namespace Phabalicious\Exception;

use Phabalicious\Validation\ValidationErrorBagInterface;

class ValidationFailedException extends \Exception
{
    private $validationErrors;
    /**
     * ValidationFailedException constructor.
     *
     * @param \Phabalicious\Validation\ValidationErrorBagInterface $validation_errors
     */
    public function __construct(ValidationErrorBagInterface $validation_errors)
    {
        $this->validationErrors = $validation_errors;
        parent::__construct(implode("\n", $validation_errors->getErrors()));
    }

    public function getValidationErrors()
    {
        return $this->validationErrors->getErrors();
    }

    public function __toString()
    {
        return implode("\n", $this->validationErrors->getErrors());
    }
}