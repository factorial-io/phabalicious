<?php


namespace Phabalicious\Validation;

use Phabalicious\Validation\ValidationErrorBagInterface;

class ValidationService
{

    private $config;
    private $errors;

    /**
     * ValidationService constructor.
     *
     * @param array $config
     * @param \Phabalicious\Validation\ValidationErrorBagInterface $errors
     */
    public function __construct(array $config, ValidationErrorBagInterface $errors)
    {
        $this->config = $config;
        $this->errors = $errors;
    }

    public function hasKey(string $key, string $message)
    {
        if (!isset($this->config[$key])) {
            $this->errors->addError($message);
        }
    }
}