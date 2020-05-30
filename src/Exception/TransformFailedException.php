<?php


namespace Phabalicious\Exception;

class TransformFailedException extends \Exception
{

    /**
     * TransformFailedException constructor.
     * @param string $filename
     * @param \Exception $e
     */
    public function __construct(string $filename, \Exception $e)
    {
        parent::__construct(sprintf("Transform failed for file `%s`", $filename), 0, $e);
    }
}
