<?php

namespace Phabalicious\Configuration\Storage;

class VisitorData
{

    /**
     * @var \Phabalicious\Configuration\Storage\Node
     */
    protected $value;

    /**
     * @var array|mixed
     */
    protected $stack;

    /**
     * @param array $stack
     * @param \Phabalicious\Configuration\Storage\Node $value
     */
    public function __construct(array $stack, Node $value)
    {
        $this->stack = $stack;
        $this->value = $value;
    }

    /**
     * @return \Phabalicious\Configuration\Storage\Node
     */
    public function getValue(): \Phabalicious\Configuration\Storage\Node
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
