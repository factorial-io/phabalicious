<?php

namespace Phabalicious\Configuration;

// phpcs:ignoreFile

class HostConfig extends HostConfigAbstract
{
    #[\ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        return $this->data->offsetGet($offset);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        $this->data->offsetSet($offset, $value);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        $this->data->offsetUnset($offset);
    }
}
