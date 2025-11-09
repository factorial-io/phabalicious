<?php

namespace Phabalicious\Configuration\Storage;

class VisitorData
{
    /**
     * @var Node
     */
    protected $value;

    /**
     * @var array|mixed
     */
    protected $stack;

    public function __construct(array $stack, Node $value)
    {
        $this->stack = $stack;
        $this->value = $value;
    }

    public function getValue(): Node
    {
        return $this->value;
    }

    /**
     * @return array|mixed
     */
    public function getStack(): mixed
    {
        return $this->stack;
    }
}
