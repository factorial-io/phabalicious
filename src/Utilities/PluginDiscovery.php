<?php

namespace Phabalicious\Utilities;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Comparator;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\MismatchedVersionException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

class PluginDiscovery
{
    /**
     * @var ClassLoader|null;
     */
    protected static $autoloader;

    public static function discover(
        $application_version,
        $paths,
        $interface_to_implement,
        $prefix,
        LoggerInterface $logger,
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
        LoggerInterface $logger,
    ) {
        $application_version = Utilities::getNextStableVersion($application_version);

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
            if ('php' !== pathinfo($filename, PATHINFO_EXTENSION)) {
                continue;
            }
            $logger->debug('Inspecting php-file '.$filename);
            $st = get_declared_classes();
            require_once $path.'/'.$filename;
            $diff = array_diff(get_declared_classes(), $st);

            foreach ($diff as $class) {
                $reflection = new \ReflectionClass($class);
                $is_instantiable = $reflection->isInstantiable();
                $implements_plugin_interface = $reflection
                    ->implementsInterface('Phabalicious\Utilities\PluginInterface');
                $implements_needed_interface = $reflection
                    ->implementsInterface($interface_to_implement);

                $logger->debug(sprintf(
                    '%s is instantiable: %s',
                    $class,
                    $is_instantiable ? 'YES' : 'NO'
                ));
                $logger->debug(sprintf(
                    '%s implements PluginInterface: %s',
                    $class,
                    $implements_plugin_interface ? 'YES' : 'NO'
                ));
                $logger->debug(sprintf(
                    '%s implements implements %s: %s',
                    $class,
                    $interface_to_implement,
                    $implements_needed_interface ? 'YES' : 'NO'
                ));

                if ($is_instantiable
                    && $implements_plugin_interface
                    && $implements_needed_interface
                ) {
                    if (Comparator::greaterThan($class::requires(), $application_version)) {
                        $logger->error(sprintf(
                            'Plugin `%s` requires %s, phab version is %s!',
                            $class,
                            $class::requires(),
                            $application_version
                        ));

                        throw new MismatchedVersionException(sprintf('Could not use plugin from %s. %s is required, current app is %s', $path.'/'.$filename, $class::requires(), $application_version));
                    }

                    $instance = new $class();
                    $result[$instance->getName()] = $instance;
                    $logger->notice('Adding phabalicious plugin '.$reflection->getName());
                }
            }
        }
    }

    public static function discoverFromFabfile(
        ContainerInterface $container,
        OutputInterface $output,
    ) {
        $application = $container->get(Application::class);
        $logger = $container->get(Logger::class);

        // Temporary config object.
        $config = new ConfigurationService($application, new NullLogger());
        $config->setOffline(true);
        try {
            $config->readConfiguration(getcwd());
            $base_path = $config->getFabfilePath();
            if ($plugins = $config->getSetting('plugins', false)) {
                if (!is_array($plugins)) {
                    $plugins = [$plugins];
                }
                /** @var AvailableMethodsAndCommandsPluginInterface[] $result */
                $result = [];
                foreach ($plugins as $path) {
                    $path = $base_path.DIRECTORY_SEPARATOR.$path;
                    $prev_count = count($result);
                    self::scanAndRegister(
                        $application->getVersion(),
                        $result,
                        $path,
                        AvailableMethodsAndCommandsPluginInterface::class,
                        'Phabalicious\\CustomPlugin\\',
                        $logger
                    );
                    if (count($result) == $prev_count) {
                        $output->writeln(sprintf('<fg=yellow>Could not load plugins from `%s`...</>', $path));
                    }
                }
                $config = $container->get(ConfigurationService::class);
                $methods = $config->getMethodFactory();

                foreach ($result as $plugin) {
                    if ($output->isVerbose()) {
                        $output->writeln(sprintf(
                            '<fg=Blue>Registering found plugin <fg=yellow>%s</> ...</>',
                            $plugin->getName()
                        ));
                    }
                    foreach ($plugin->getMethods() as $class_name) {
                        $methods->addMethod(new $class_name($logger));
                    }
                    foreach ($plugin->getCommands() as $class_name) {
                        $application->add(new $class_name($config, $methods));
                    }
                }
                if (count($result)) {
                    $output->writeln("\n");
                }
            }
        } catch (\Exception $e) {
            // Ignore exception
        }
    }
}
