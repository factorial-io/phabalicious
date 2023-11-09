<?php

namespace Phabalicious\Configuration\Storage;

class Source
{

    protected $source;

      /**
      * @param $source
      */
    public function __construct($source)
    {
        $this->source = $source;
    }

      /**
       * @return mixed
       */
    public function getSource(): mixed
    {
        return $this->source;
    }
}
