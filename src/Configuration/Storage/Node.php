<?php

namespace Phabalicious\Configuration\Storage;

use Phabalicious\Utilities\Utilities;
use Symfony\Component\Yaml\Yaml;

class Node implements \IteratorAggregate, \ArrayAccess
{
    protected $value;
    protected $source;

    public function __construct($value, $source)
    {
        if (is_array($value)) {
            $this->value = array_map(function ($elem) use ($source) {
                return new Node($elem, $source);
            }, $value);
        } else {
            $this->value = $value;
        }
        $this->source = Sources::getSource($source);
    }

    public function __clone()
    {
        if ($this->isArray()) {
            foreach ($this->value as $key => $val) {
                $this->value[$key] = clone $this->value[$key];
            }
        }
    }

    public static function parseYamlFile(string $file): Node
    {
        $data = Yaml::parseFile($file);
        return new Node($data, $file);
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->isArray() ? $this->asArray() : $this->value;
    }

    /**
     * @return Source
     */
    public function getSource():Source
    {
        return $this->source;
    }

    /**
     * @param mixed $value
     *
     * @return Node
     */
    public function setValue($value): Node
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @param mixed $source
     *
     * @return Node
     */
    public function setSource($source): Node
    {
        $this->source = $source;
        return $this;
    }

    public function isArray(): bool
    {
        return is_array($this->value);
    }

    private function isAssocArray(): bool
    {
        return Utilities::isAssocArray($this->value);
    }

    public function merge(Node $overrides): Node
    {
        $saved = Store::saveProtectedProperties($this);
        $result = $this->mergeImpl($overrides);
        if (!empty($saved)) {
            Store::restoreProtectedProperties($result, $saved);
        }
        return $result;
    }

    protected function mergeImpl(Node $overrides): Node
    {
        if (!$this->isArray() || !$overrides->isArray()) {
            $this->value = $overrides->value;
            $this->source = $overrides->source;
        } else {
            /** @var Node $override */
            foreach ($overrides as $key => $override) {
                if ($override->isAssocArray() && $this->isAssocArray()) {
                    if ($this->has($key)) {
                        $this->get($key)->mergeImpl($override);
                    } else {
                        $this->set($key, $override);
                    }
                } else {
                    $this->set($key, $override);
                }
            }
        }

        return $this;
    }

    public function baseonTop(Node $overrides): Node
    {
        $saved = Store::saveProtectedProperties($this);
        $result = $this->baseOntopImpl($overrides);
        if (!empty($saved)) {
            Store::restoreProtectedProperties($result, $saved);
        }
        return $result;
    }

    protected function baseOntopImpl($base): Node
    {
        $left = $base;
        $right = $this;

        foreach ($left as $key => $value) {
            // If the value is not present in right, add it:
            if (!$right->has($key)) {
                $right->set($key, $value);
            } else {
                // Right has the value, check if an associative array
                if ($right->get($key)->isAssocArray()) {
                    $right->get($key)->baseOntopImpl($value);
                }
                // Otherwise skip it, as we do not merge plain arrays.
            }
        }

        return $this;
    }


    public function getOrCreate(string $key, $default): Node
    {
        if (!$this->has($key)) {
            $this->set($key, new Node($default, $this->getSource()));
        }
        return $this->get($key);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->value);
    }

    public function has($key): bool
    {
        return isset($this->value[$key]);
    }

    public function get($key, $default = null): ?Node
    {
        if (isset($this->value[$key])) {
            return $this->value[$key];
        }

        return is_null($default) ? null : new Node($default, $this->source);
    }

    public function set(string $key, Node $value): void
    {
        $this->value[$key] = $value;
    }

    public function offsetGet($offset)
    {
        return $this->get($offset)->getValue();
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetSet($offset, $value)
    {
        if (!$value instanceof Node) {
            $value = new Node($value, $this->getSource());
        }
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->value[$offset]);
    }

    public function findNodes(string $needle, $max_levels): \Generator
    {
        if (!$this->isArray() || $max_levels < 0) {
            return;
        }
        foreach ($this->value as $key => $node) {
            if ($key === $needle) {
                yield $key => $node;
            } elseif ($node->isArray()) {
                yield from $node->findNodes($needle, $max_levels - 1);
            }
        }
    }

    public function iterateBackwardsOverValues(): \Generator
    {
        if ($this->isArray()) {
            for (end($this->value); ($key=key($this->value))!==null; prev($this->value)) {
                yield $key => current($this->value)->getValue();
            }
        } else {
            yield $this->getValue();
        }
    }

    public static function mergeData(Node $a, Node $b): Node
    {
        $c = self::clone($a);
        return $c->merge($b);
    }

    public function asArray(): array
    {
        $result = [];
        foreach ($this->value as $key => $value) {
            $result[$key] = $value->isArray() ? $value->asArray() : $value->getValue();
        }
        return $result;
    }

    public static function clone(Node $node): Node
    {
        return clone ($node);
    }

    public function find(string $dotted_key): ?Node
    {
        $keys = explode('.', $dotted_key);
        $node = $this;
        foreach ($keys as $key) {
            if (!$node->has($key)) {
                return null;
            }
            $node = $node->get($key);
        }
        return $node;
    }

    public function getProperty(string $dotted_key, $default_value = null)
    {
        $node = $this->find($dotted_key);
        return  $node ? $node->getValue() ?? $default_value : $default_value;
    }

    public function setProperty(string $dotted_key, $new_value)
    {
        $node = $this->find($dotted_key);
        if (!$node) {
            throw new \InvalidArgumentException(sprintf("Could not find key %s in data!", $dotted_key));
        }
        $node->setValue($new_value);
    }

    public function wrapIntoArray()
    {
        $this->value = [ new Node($this->value, $this->source) ];
    }

    public function push($value)
    {
        $this->value[] = $value instanceof Node ? $value : new Node($value, $this->source);
    }

    public function transformToArray()
    {
        if (!$this->isArray()) {
            $this->value = [ new Node($this->value, $this->source)];
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    public function unset(string $key): void
    {
        unset($this->value[$key]);
    }

    public function expandReplacements(array &$replacements, array $ignore_keys)
    {
        if (!$this->isArray()) {
            if (is_string($this->value)) {
                $this->value = Utilities::expandString($this->value, $replacements);
            }
            return;
        }
        foreach ($this->value as $key => $value) {
            if (in_array($key, $ignore_keys, true)) {
                continue;
            }
            $value->expandReplacements($replacements, $ignore_keys);
        }
    }

    public function visit($stack = []): \Generator
    {
        yield new VisitorData($stack, $this);
        if ($this->isArray()) {
            foreach ($this->value as $key => $value) {
                $stack[] = $key;
                yield from $value->visit($stack);
                array_pop($stack);
            }
        }
    }
}
