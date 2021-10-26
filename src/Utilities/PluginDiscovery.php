<?php

namespace Phabalicious\Utilities;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
use MyProject\Container;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Method\MethodFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Reference;

class PluginDiscovery
{

    /**
     * @var ClassLoader|null;
     */
    protected static $autoloader = null;

    public static function discover(
        $application_version,
        $paths,
        $interface_to_implement,
        $prefix,
        LoggerInterface $logger
    ) {
        $result = [];
        foreach ($paths as $path) {
            self::scanAndRegister($application_version, $result, $path, $interface_to_implement, $prefix, $logger);
        }

        return $result;
    }

    protected static function scanAndRegister(
        $application_version,
        &$result,
        $path,
        $interface_to_implement,
        $prefix,
        LoggerInterface $logger
    ) {
        if (!is_dir($path)) {
            $logger->warning(sprintf('PluginDiscovery: %s is not a directory, aborting...', $path));
            return;
        }

        // Get autoloader and register plugins namespace.
        // We cant use the autoloader part of the phar, as it is optimized using the classmap authoritative mode
        // which prevents dynamic loading of classes.
        if (!self::$autoloader) {
            self::$autoloader = new ClassLoader();
            self::$autoloader->register(true);
        }
        $realpath = realpath($path);
        $logger->debug(sprintf('Registering %s for namespace %s', $realpath, $prefix));
        self::$autoloader->addPsr4($prefix, $realpath);

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

    public static function discoverFromFabfile(
        ContainerInterface $container
    ) {
        $application = $container->get(Application::class);
        $logger = $container->get(Logger::class);

        // Temporary config object.
        $config = new ConfigurationService($application, new NullLogger());
        $config->setOffline(true);
        try {
            $config->readConfiguration(getcwd());
            if ($plugins = $config->getSetting('plugins', false)) {
                if (!is_array($plugins)) {
                    $plugins = [ $plugins ];
                }
                /** @var \Phabalicious\Utilities\AvailableMethodsAndCommandsPluginInterface[] $result */
                $result = [];
                foreach ($plugins as $path) {
                    self::scanAndRegister(
                        $application->getVersion(),
                        $result,
                        $path,
                        AvailableMethodsAndCommandsPluginInterface::class,
                        'Phabalicious\\CustomPlugin\\',
                        $logger
                    );
                }
                $config = $container->get(ConfigurationService::class);
                $methods = $config->getMethodFactory();

                foreach ($result as $plugin) {
                    foreach ($plugin->getMethods() as $class_name) {
                        $methods->addMethod(new $class_name($logger));
                    }
                    foreach ($plugin->getCommands() as $class_name) {
                        $application->add(new $class_name($config, $methods));
                    }
                }
            }
        } catch (\Exception $e) {
            ; // Ignore exception
        }
    }
}
