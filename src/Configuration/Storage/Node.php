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
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @param mixed $source
     *
     * @return Node
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    public function isArray()
    {
        return is_array($this->value);
    }

    private function isAssocArray()
    {
        return Utilities::isAssocArray($this->value);
    }

    public function merge(Node $overrides)
    {
        if (!$this->isArray() || !$overrides->isArray()) {
            $this->value = $overrides->value;
            $this->source = $overrides->source;
        } else {
            /** @var Node $override */
            foreach ($overrides as $key => $override) {
                if ($override->isAssocArray() && $this->isAssocArray()) {
                    if ($this->has($key)) {
                        $this->value[$key]->merge($override);
                    } else {
                        $this->value[$key] = $override;
                    }
                } else {
                    $this->value[$key] = $override;
                }
            }
        }

        return $this;
    }

    public function baseOntop($base)
    {
        $left = $base;
        $right = $this;

        foreach ($left as $key => $value) {
            // If the value is not present in right, add it:
            if (!$right->has($key)) {
                $right->set($key, $value);
            } else {
                // right has the value, check if an array
                if ($right->get($key)->isArray()) {
                    $right->get($key)->baseOntop($value);
                } else {
                    // Right has a value, which we keep.
                }
            }
        }

        return $this;
    }


    public function getOrCreate(string $key, $default)
    {
        if (!isset($this->value[$key])) {
            $this->value[$key] = new Node($default, $this->getSource());
        }
        return $this->value[$key];
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->value);
    }

    public function has($key)
    {
        return isset($this->value[$key]);
    }

    public function get($key, $default = null)
    {
        return $this->value[$key] ?? new Node($default, $this->source);
    }

    public function set(string $key, Node $value)
    {
        $this->value[$key] = $value;
    }

    public function offsetGet($offset)
    {
        return $this->value[$offset]->getValue();
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetSet($offset, $value)
    {
        if (!$value instanceof Node) {
            $value = new Node($value, $this->getSource());
        }
        $this->value[$offset] = $value;
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

    public function getProperty(string $dotted_key, $default_value = null)
    {
        $keys = explode('.', $dotted_key);
        $node = $this;
        foreach ($keys as $key) {
            if (!$node->has($key)) {
                return $default_value;
            }
            $node = $node->get($key);
        }
        return $node->getValue();
    }

    public function setProperty(string $dotted_key, $new_value)
    {
        $keys = explode('.', $dotted_key);
        $node = $this;
        foreach ($keys as $key) {
            if (!$node->has($key)) {
                throw new \InvalidArgumentException(sprintf("Could not find key %s in data!", $dotted_key));
            }
            $node = $node->get($key);
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

    public function transformtoArray()
    {
        if (!$this->isArray()) {
            $this->value = [ new Node($this->value, $this->source)];
        }
    }

    public function isEmpty()
    {
        return empty($this->value);
    }

    public function unset(string $key)
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
}
