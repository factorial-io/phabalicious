<?php

namespace Phabalicious\Configuration;

// phpcs:ignoreFile

if (PHP_VERSION_ID < 81000) {

    class HostConfig extends HostConfigAbstract
    {

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
        public function offsetSet($offset, $value): void
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
        public function offsetUnset($offset): void
        {
            unset($this->data[$offset]);
        }
    }
}
else {

    class HostConfig extends HostConfigAbstract
    {

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
        public function offsetGet($offset): mixed
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
        public function offsetSet(mixed $offset, mixed $value): void
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
        public function offsetUnset(mixed $offset): void
        {
            unset($this->data[$offset]);
        }
    }
}
