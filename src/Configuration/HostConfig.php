<?php

namespace Phabalicious\Configuration;

use Phabalicious\Method\BaseMethod;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;

class HostConfig implements \ArrayAccess
{
    private $data;

    private $shell;

    private $configurationService;

    public function __construct($data, ShellProviderInterface $shell, ConfigurationService $parent)
    {
        $this->configurationService = $parent;
        $this->data = $data;
        $this->shell = $shell;

        $shell->setHostConfig($this);
    }

    public function shell(): ShellProviderInterface
    {
        return $this->shell;
    }

    public function getConfigurationService(): ConfigurationService
    {
        return $this->configurationService;
    }

    public function raw(): array
    {
        return $this->data;
    }

    /**
     * Whether a offset exists
     *
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Offset to retrieve
     *
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     *
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * Offset to set
     *
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     *
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     *
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
         unset($this->data[$offset]);
    }


    public function get($key, $default = null)
    {
        if (empty($this->data[$key])) {
            return $default;
        }
        return $this->data[$key];
    }

    public function isType(string $type)
    {
        return $this['type'] == $type;
    }

    public function isMethodSupported(BaseMethod $method)
    {
        foreach ($this->get('needs') as $need) {
            if ($method->supports($need)) {
                return true;
            }
        }
        return false;
    }

    public function setChild(string $parent, string $child, $value)
    {
        $this->data[$parent][$child] = $value;
    }

    public function setProperty(string $key, string $value)
    {
        Utilities::setProperty($this->data, $key, $value);
    }
}
