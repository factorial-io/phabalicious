<?php

namespace Phabalicious\Configuration\Storage;

class Source
{
    protected $source;

    public function __construct($source)
    {
        $this->source = $source;
    }

    public function getSource(): mixed
    {
        return $this->source;
    }
}
