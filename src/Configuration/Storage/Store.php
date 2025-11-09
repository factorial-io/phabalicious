<?php

namespace Phabalicious\Configuration\Storage;

class Store extends Node
{
    protected static $protectedProperties = [];

    public static function setProtectedProperties(Node $data, $key_name)
    {
        if (!$data->has($key_name)) {
            return;
        }
        $keys = $data->get($key_name)->getValue();
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        self::$protectedProperties = $keys;
    }

    public static function resetProtectedProperties()
    {
        self::$protectedProperties = [];
    }

    public static function saveProtectedProperties(Node $data)
    {
        $result = [];
        foreach (self::$protectedProperties as $key) {
            if ($node = $data->find($key)) {
                $result[$key] = Node::clone($node);
            } else {
                $result[$key] = new Node(null, 'protected property');
            }
        }

        return $result;
    }

    public static function restoreProtectedProperties(Node $data, array $saved)
    {
        /** @var Node $v */
        foreach ($saved as $key => $v) {
            if ($node = $data->find($key)) {
                $node->value = $v->value;
                $node->source = $v->source;
            }
        }
    }
}
