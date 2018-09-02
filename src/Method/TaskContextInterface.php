<?php

namespace Phabalicious\Method;

interface TaskContextInterface {

    public function set(string $key, $data);

    public function get(string $key);
}