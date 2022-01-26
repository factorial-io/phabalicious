<?php

namespace Phabalicious\Validation;

class ValidationErrorBag implements ValidationErrorBagInterface
{
    private $errors = [];
    private $keysWithErrors = [];
    private $warnings = [];

    public function addError(string $key, string $error_message)
    {
        $this->keysWithErrors[] = $key;
        $this->errors[] = $error_message;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) !== 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getKeysWithErrors(): array
    {
        return $this->keysWithErrors;
    }

    public function addWarning(string $key, string $warning_message)
    {
        $this->warnings[$key] = $warning_message;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function addErrorBag(ValidationErrorBagInterface $validation_errors)
    {
        foreach ($validation_errors->getErrors() as $key => $message) {
            $this->addError($key, $message);
        }
        foreach ($validation_errors->getWarnings() as $key => $message) {
            $this->addWarning($key, $message);
        }
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }
}
