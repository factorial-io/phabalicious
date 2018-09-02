<?php

namespace Phabalicious\Configuration;

use Composer\Semver\Comparator;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Method\MethodFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;
use Wikimedia\Composer\Merge\NestedArray;

class ConfigurationService
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $application;

    private $methods;

    private $fabfilePath;

    private $dockerHosts;
    private $hosts;
    private $settings;

    private $cache;

    public function __construct(Application $application, LoggerInterface $logger)
    {
        $this->application = $application;
        $this->logger = $logger;
    }

    public function setMethodFactory(MethodFactory $method_factory) {
        $this->methods = $method_factory;
    }

    public function readConfiguration(string $path, string $override = ''): bool
    {
        if (!empty($this->settings)) {
            return true;
        }

        $fabfile = !empty($override) ? $override : $this->findFabfilePath([
            'fabfile.yml',
            '.fabfile.yml',
            'fabfile.yaml',
            '.fabfile.yaml'
        ], $path);

        if (!$fabfile || !file_exists($fabfile)) {
            if (!empty($override)) {
                throw new FabfileNotFoundException("Could not find fabfile at '" . $override . "'");
            } else {
                throw new FabfileNotFoundException("Could not find any fabfile at '" . $path . "'");
            }
        }

        $this->setFabfilePath(dirname($fabfile));

        $data = $this->readFile($fabfile);
        if (!$data) {
            throw new FabfileNotReadableException("Could not read from '" . $fabfile . "'");
        }

        if ($local_override_file = $this->findFabfilePath(['fabfile_local.yaml', 'fabfile_local.yml'])) {
            $override_data = $this->readFile($local_override_file);
            $data = $this->mergeData($data, $override_data);
        }

        $defaults = [
            'needs' => ['git', 'ssh', 'drush7', 'files'],
            'common' => [],
        ];

        $data = $this->applyDefaults($data, $defaults);

        /**
         * @var \Phabalicious\Method\MethodInterface $method
         */
        foreach ($this->methods->all() as $method) {
            $data = $this->mergeData($data, $method->getGlobalSettings());
        }

        $this->settings = $this->resolveInheritance($data, $data);
        $this->hosts = $this->getSetting('hosts', []);
        $this->dockerHosts = $this->getSetting('dockerHosts', []);

        return true;
    }

    protected function findFabfilePath(array $candidates, string $path = '')
    {
        if (empty($path)) {
            $path = $this->getFabfilePath();
        }
        $depth = 0;
        while ($depth <= 3) {
            foreach ($candidates as $candidate) {
                if (file_exists($path . '/' . $candidate)) {
                    return $path . '/' . $candidate;
                }
            }
            $depth++;
            $path = dirname($path);
        }

        return false;
    }

    protected function readFile(string $file)
    {
        $cid = 'yaml:' . $file;
        if (isset($this->cache[$cid])) {
            return $this->cache[$cid];
        }

        $data = Yaml::parseFile($file);
        if ($data && isset($data['requires'])) {
            $required_version = $data['requires'];
            $app_version = $this->application->getVersion();
            if (Comparator::greaterThan($required_version, $app_version)) {
                throw new MismatchedVersionException('Could not read file ' . $file . ' because of version mismatch: ' . $app_version . '<' . $required_version);
            }
        }

        $this->cache[$cid] = $data;
        return $data;
    }

    private function setFabfilePath(string $path)
    {
        $this->fabfilePath = $path;
    }

    public function getFabfilePath()
    {
        return $this->fabfilePath;
    }

    private function mergeData(array $data, array $override_data): array
    {
        return NestedArray::mergeDeep($data, $override_data);
    }

    private function applyDefaults(array $data, array $defaults)
    {
        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    private function resolveInheritance(array $data, $lookup): array
    {
        if (!isset($data['inheritsFrom'])) {
            return $data;
        }
        $inheritsFrom = $data['inheritsFrom'];
        if (!is_array($inheritsFrom)) {
            $inheritsFrom = [ $inheritsFrom ];
        }
        unset($data['inheritsFrom']);

        foreach (array_reverse($inheritsFrom) as $resource) {
            $add_data = false;
            if (isset($lookup[$resource])) {
                $add_data = $lookup[$resource];
            } elseif (strpos($resource, 'http') !== false) {
                $add_data = Yaml::parse($this->readHttpResource($resource));
            } elseif (file_exists($this->getFabfilePath() . '/' . $resource)) {
                $add_data = $this->readFile($this->getFabfilePath() . '/' . $resource);
            }
            if ($add_data) {
                if (isset($add_data['inheritsFrom'])) {
                    $add_data = $this->resolveInheritance($add_data, $lookup);
                }

                $data = $this->mergeData($add_data, $data);
            }
        }

        return $data;
    }

    public function getSetting(string $key, $default_value = null)
    {
        $value = $default_value;
        $keys = explode('.', $key);
        $data = $this->settings;
        $first_run = true;
        foreach ($keys as $sub_key) {
            if ($first_run) {
                $value = $data;
                $first_run = false;
            }
            if (isset($value[$sub_key])) {
                $value = $value[$sub_key];
            } else {
                return $default_value;
            }
        }

        return $value;
    }

    public function readHttpResource(string $resource):string
    {
        $cid = 'resource:' . $resource;

        if (isset($this->cache[$cid])) {
            return $this->cache[$cid];
        }
        $contents = file_get_contents($resource);
        $cache_file = getenv("HOME")
            . '/.phabalicious/' . md5($resource)
            . '.' . pathinfo($resource, PATHINFO_EXTENSION);

        if (!is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0777, TRUE);
        }

        if ($contents === false && file_exists($cache_file)) {
            $contents = file_get_contents($cache_file);
        } elseif ($contents !== false) {
            file_put_contents($cache_file, $contents);
        }
        $this->cache[$cid] = $contents;

        return $contents;
    }

    public function getHostConfig(string $config_name)
    {
        $cid = 'host:' . $config_name;

        if (!empty($this->cache[$cid])) {
            return $this->cache[$cid];
        }

        if (empty($this->hosts[$config_name])) {
            throw new MissingHostConfigException('Could not find host configuration for ' . $config_name);
        }

        $data = $this->hosts[$config_name];
        $data = $this->resolveInheritance($data, $this->hosts);

        $defaults = [
            'needs' => $this->getSetting('needs'),
            'config_name' => $config_name,
        ];

        /**
         * @var \Phabalicious\Method\MethodInterface $method
         */
        foreach ($this->methods->all() as $method) {
            $defaults = $this->mergeData($defaults, $method->getDefaultConfig($this));
        }

        $data = $this->applyDefaults($data, $defaults);



        $this->cache[$cid] = $data;
        return $data;
    }

    public function getDockerConfig(string $config_name)
    {
        $cid = 'dockerhost:' . $config_name;

        if (!empty($this->cache[$cid])) {
            return $this->cache[$cid];
        }

        if (empty($this->dockerHosts[$config_name])) {
            throw new MissingDockerHostConfigException('Could not find docker host configuration for ' . $config_name);
        }

        $data = $this->dockerHosts[$config_name];
        $data = $this->resolveInheritance($data, $this->dockerHosts);

        $this->cache[$cid] = $data;
        return $data;
    }

}