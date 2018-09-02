<?php

namespace Phabalicious\Configuration;

class ValidationErrorBag implements ValidationErrorBagInterface
{
    private $errors = [];

    public function addError(string $error_message)
    {
        $this->errors[] = $error_message;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) === 0;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}