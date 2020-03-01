<?php

namespace Phabalicious\Utilities;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Scaffolder\DataTransformerInterface;
use Symfony\Component\Console\Application;

class PluginDiscovery
{

    public static function discover(Application $application, $paths, $interface_to_implement)
    {
        $result = [];
        foreach ($paths as $path) {
            self::scanAndRegister($application, $result, $path, $interface_to_implement);
        }

        return $result;
    }

    protected static function scanAndRegister(Application $application, &$result, $path, $interface_to_implement)
    {
        if (!is_dir($path)) {
            return;
        }
        
        // Find composer.json
        $directory = __DIR__;
        $root = null;
        do {
            $directory = dirname($directory);
            $vendor = $directory . '/vendor';
            if (is_dir($vendor)) {
                $root = $directory;
            }
        } while (is_null($root) && $directory !== '/');
        if ($root == null) {
            throw new \RuntimeException('Could not detect vendor-directory');
        }
        
        // Get autoloader and register plugins namespace.
        $autoloader = require($root . '/vendor/autoload.php');
        $autoloader->addPsr4('Phabalicious\Scaffolder\Transformers\\', realpath($path));

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
                if ($reflection->isInstantiable()
                    && $reflection->implementsInterface('Phabalicious\Utilities\PluginInterface')
                    && $reflection->implementsInterface($interface_to_implement)
                ) {
                    if (Comparator::greaterThan($class::requires(), $application->getVersion())) {
                        throw new MismatchedVersionException(
                            sprintf(
                                'Could not use plugin from %s. %s is required, current app is %s',
                                $path . '/' . $filename,
                                $class::requires(),
                                $application->getVersion()
                            )
                        );
                    }

                    $instance = new $class;
                    $result[$instance->getName()] = $instance;
                }
            }
        }
    }
}
