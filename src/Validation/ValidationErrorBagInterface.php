<?php

namespace Phabalicious\Validation;

interface ValidationErrorBagInterface
{

    public function addError(string $error_message);

    public function hasErrors(): bool;

    public function getErrors();


}