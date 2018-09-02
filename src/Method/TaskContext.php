<?php

namespace Phabalicious\Method;

class TaskContext implements TaskContextInterface
{
    private $data = [];

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get(string $key)
    {
         return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}