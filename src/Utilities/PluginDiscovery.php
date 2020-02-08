<?php

namespace Phabalicious\Utilities;

use Phabalicious\Scaffolder\DataTransformerInterface;

class PluginDiscovery
{

    public static function discover($paths, $interface_to_implement)
    {
        $result = [];
        foreach ($paths as $path) {
            self::scanAndRegister($result, $path, $interface_to_implement);
        }

        return $result;
    }

    protected static function scanAndRegister(&$result, $path, $interface_to_implement)
    {
        if (!is_dir($path)) {
            return;
        }
        $contents = scandir($path);
        foreach ($contents as $filename) {
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            $st = get_declared_classes();
            require_once $path . '/' . $filename;
            $diff = array_diff(get_declared_classes(), $st);

            foreach ($diff as $class) {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isInstantiable() && $reflection->implementsInterface($interface_to_implement)) {
                    $instance = new $class;
                    $result[$instance->getName()] = $instance;
                }
            }
        }
    }
}
