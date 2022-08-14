<?php


namespace Phabalicious\Validation;

use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;

class ValidationService
{

    private $config;
    private $errors;
    private $prefixMessage;

    /**
     * ValidationService constructor.
     *
     * @param array|\ArrayAccess $config
     * @param \Phabalicious\Validation\ValidationErrorBagInterface $errors
     * @param string $prefix_message
     */
    public function __construct($config, ValidationErrorBagInterface $errors, string $prefix_message)
    {
        $this->config = $config;
        $this->errors = $errors;
        $this->prefixMessage = $prefix_message;
    }

    public function getErrorBag() : ValidationErrorBagInterface
    {
        return $this->errors;
    }
    public function hasKey(string $key, string $message): bool
    {
        if (is_null(Utilities::getProperty($this->config, $key, null))) {
            $this->errors->addError($key, 'Missing key '. $key . ' in ' . $this->prefixMessage . ': ' . $message);
            return false;
        }
        return true;
    }

    public function hasKeys(array $keys)
    {
        foreach ($keys as $key => $message) {
            $this->hasKey($key, $message);
        }
    }

    public function deprecate(array $keys)
    {
        foreach ($keys as $key => $message) {
            if (!is_null(Utilities::getProperty($this->config, $key, null))) {
                $this->errors->addWarning($key, $message);
            }
        }
    }

    public function arrayContainsKey(string $key, array $haystack, string $message)
    {
        if (!isset($haystack[$key])) {
            $this->errors->addError($key, 'key '. $key . ' not found. ' . $this->prefixMessage . ': ' . $message);
        }
    }
    public function isArray(string $key, string $message)
    {
        if ($this->hasKey($key, $message) && !is_array(Utilities::getProperty($this->config, $key))) {
            $this->errors->addError($key, 'key '. $key . ' not an array in ' . $this->prefixMessage . ': ' . $message);
        }
    }

    public function isOneOf(string $key, array $candidates)
    {
        if (!$this->hasKey($key, 'Candidates: ' . implode(', ', $candidates))) {
            return false;
        }
        $value = Utilities::getProperty($this->config, $key);
        if (!in_array($value, $candidates)) {
            $this->errors->addError(
                $key,
                sprintf(
                    'key %s has unrecognized value: `%s` in %s: Candidates are %s',
                    $key,
                    $value,
                    $this->prefixMessage,
                    implode(', ', $candidates)
                )
            );
        }
    }

    public function checkForValidFolderName(string $key)
    {
        if (!$this->hasKey($key, 'Missing key')) {
            return false;
        }
        $root_folder = Utilities::getProperty($this->config, $key);
        if ($root_folder !== '/' && substr($root_folder, -1) === DIRECTORY_SEPARATOR) {
            $this->errors->addError(
                $key,
                sprintf('key `%s` is ending with a directory separator, please change!', $key)
            );
            return false;
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getNewValidatorFor(string $key)
    {
        $this->isArray($key, 'Sub-config needs to be an array');
        if (is_array($this->config[$key])) {
            return new ValidationService($this->config[$key], $this->getErrorBag(), $this->prefixMessage);
        }

        return false;
    }

    public function hasAtLeast(array $keys, string $message)
    {

        $has_key = false;
        foreach ($keys as $key) {
            if (isset($this->config[$key])) {
                $has_key = true;
            }
        }

        if (!$has_key) {
            $this->errors->addError(implode(', ', $keys), $message);
        }

        return $has_key;
    }
}
