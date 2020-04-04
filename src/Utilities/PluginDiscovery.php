<?php

namespace Phabalicious\Utilities;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Scaffolder\DataTransformerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;

class PluginDiscovery
{

    public static function discover($application_version, $paths, $interface_to_implement, LoggerInterface $logger)
    {
        $result = [];
        foreach ($paths as $path) {
            self::scanAndRegister($application_version, $result, $path, $interface_to_implement, $logger);
        }

        return $result;
    }

    protected static function scanAndRegister(
        $application_version,
        &$result,
        $path,
        $interface_to_implement,
        LoggerInterface $logger
    ) {
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

        $logger->debug('Found autoloader at ' . $root . '/vendor/autoload.php');

        // Get autoloader and register plugins namespace.
        $autoloader = require($root . '/vendor/autoload.php');
        $autoloader->addPsr4('Phabalicious\Scaffolder\Transformers\\', realpath($path));

        $contents = scandir($path);
        foreach ($contents as $filename) {
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }
            $logger->debug('Inspecting php-file ' . $filename);
            $st = get_declared_classes();
            require_once $path . '/' . $filename;
            $diff = array_diff(get_declared_classes(), $st);

            foreach ($diff as $class) {
                $reflection = new \ReflectionClass($class);
                if ($reflection->isInstantiable()
                    && $reflection->implementsInterface('Phabalicious\Utilities\PluginInterface')
                    && $reflection->implementsInterface($interface_to_implement)
                ) {
                    if (Comparator::greaterThan($class::requires(), $application_version)) {
                        throw new MismatchedVersionException(
                            sprintf(
                                'Could not use plugin from %s. %s is required, current app is %s',
                                $path . '/' . $filename,
                                $class::requires(),
                                $application_version
                            )
                        );
                    }

                    $instance = new $class;
                    $result[$instance->getName()] = $instance;
                    $logger->debug('Adding phabalicious plugin ' . $reflection->getName());
                }
            }
        }
    }
}
