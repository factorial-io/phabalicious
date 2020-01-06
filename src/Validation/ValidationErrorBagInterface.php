<?php

namespace Phabalicious\Validation;

interface ValidationErrorBagInterface
{

    public function addError(string $key, string $error_message);

    public function hasErrors(): bool;

    public function getErrors();

    public function addWarning(string $key, string $warning_message);

    public function getWarnings();
}
