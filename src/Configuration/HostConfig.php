<?php

namespace Phabalicious\Configuration;

// phpcs:ignoreFile

class HostConfig extends HostConfigAbstract
{

    public function offsetGet($offset)
    {
        return $this->data->offsetGet($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->data->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->data->offsetUnset($offset);
    }
}
