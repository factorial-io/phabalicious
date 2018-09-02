<?php

namespace Phabalicious\Configuration;

interface ValidationErrorBagInterface
{

    public function addError(string $error_message);

    public function hasErrors(): bool;

    public function getErrors();


}