<?php

namespace Phabalicious\ConfigurationService;

use Phabalicious\Configuration\Storage\Node;

class DeprecatedValueMapping
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $old;

    /**
     * @var string
     */
    protected $new;

    /**
     * Ctor.
     */
    public function __construct(string $key, string $old, string $new)
    {
        $this->key = $key;
        $this->old = $old;
        $this->new = $new;
    }

    public function apply(Node $node): bool
    {
        if ($node->getValue() === $this->old) {
            $node->setValue($this->new);

            return true;
        }

        return false;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDeprecationMessage(): string
    {
        return sprintf('Value `%s` is deprecated for key `%s`, use instead `%s`.', $this->old, $this->key, $this->new);
    }
}
