<?php

namespace Phabalicious\Configuration;

use Composer\Semver\Comparator;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\TooManyShellProvidersException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\ShellProvider\LocalShellProvider;
use Phabalicious\ShellProvider\SshShellProvider;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
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

    /**
     * @var MethodFactory
     */
    private $methods;

    private $fabfilePath;

    private $dockerHosts;
    private $hosts;
    private $settings;

    /** @var array */
    private $cache;

    /** @var BlueprintConfiguration */
    private $blueprints;
    private $offlineMode = false;

    public function __construct(Application $application, LoggerInterface $logger)
    {
        $this->application = $application;
        $this->logger = $logger;
    }

    public function setMethodFactory(MethodFactory $method_factory)
    {
        $this->methods = $method_factory;
    }

    public function getMethodFactory()
    {
        return $this->methods;
    }

    /**
     * Read configuration from a file.
     *
     * @param string $path
     * @param string $override
     *
     * @return bool
     * @throws FabfileNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     */
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

        if ($local_override_file = $this->findFabfilePath(['fabfile.local.yaml', 'fabfile.local.yml'])) {
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

        $this->blueprints = new BlueprintConfiguration($this);
        if (!empty($data['blueprints'])) {
            $this->blueprints->expandVariants($data['blueprints']);
        }

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

    /**
     * @param string $file
     *
     * @return mixed
     * @throws \Phabalicious\Exception\MismatchedVersionException
     */
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
                throw new MismatchedVersionException(
                    'Could not read file ' .
                    $file .
                    ' because of version mismatch: ' .
                    $app_version . '<' .
                    $required_version
                );
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

    public function mergeData(array $data, array $override_data): array
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

    /**
     * Resolve inheritance for given data.
     *
     * @param array $data
     * @param $lookup
     *
     * @return array
     * @throws \Phabalicious\Exception\MismatchedVersionException
     */
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

        foreach ($inheritsFrom as $resource) {
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
        return Utilities::getProperty($this->settings, $key, $default_value);


    }

    public function readHttpResource(string $resource):string
    {
        $cid = 'resource:' . $resource;
        $contents = false;

        if (isset($this->cache[$cid])) {
            return $this->cache[$cid];
        }

        if (!$this->offlineMode) {
            try {
                $this->logger->info('Read remote file from ' . $resource . '`');
                $contents = file_get_contents($resource);
            } catch (\Exception $e) {
                $this->logger->warning('Could not load resource from `' . $resource . '`: ' . $e->getMessage());
                $contents = false;
            }
        }

        $cache_file = getenv("HOME")
            . '/.phabalicious/' . md5($resource)
            . '.' . pathinfo($resource, PATHINFO_EXTENSION);

        if (!is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0777, true);
        }

        if ($contents === false && file_exists($cache_file)) {
            $this->logger->info('Using cached version for `' . $resource .'`');
            $contents = file_get_contents($cache_file);
        } elseif ($contents !== false) {
            file_put_contents($cache_file, $contents);
        }
        $this->cache[$cid] = $contents;

        return $contents;
    }

    /**
     * @param string $config_name
     *
     * @return \Phabalicious\Configuration\HostConfig
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingHostConfigException
     * @throws \Phabalicious\Exception\ValidationFailedException
     * @throws \Phabalicious\Exception\TooManyShellProvidersException
     */
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
        $data = $this->validateHostConfig($config_name, $data);

        $this->cache[$cid] = $data;
        return $data;
    }

    /**
     * @param string $blueprint
     * @param string $identifier
     * @return \Phabalicious\Configuration\HostConfig
     * @throws MismatchedVersionException
     * @throws TooManyShellProvidersException
     * @throws ValidationFailedException
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     */
    public function getHostConfigFromBlueprint(string $blueprint, string $identifier)
    {
        $cid = 'blueprint:' . $blueprint . ':' . $identifier;

        if (!empty($this->cache[$cid])) {
            return $this->cache[$cid];
        }

        $template = $this->blueprints->getTemplate($blueprint);
        $data = $template->expand($identifier);

        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'blueprint');
        $validation->hasKey('configName', 'The blueprint needs a `configName` property');
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }
        $data = $this->validateHostConfig($data['configName'], $data);

        $this->cache[$cid] = $data;
        return $data;
    }

    /**
     * @param $config_name
     * @param $data
     * @return HostConfig
     * @throws MismatchedVersionException
     * @throws TooManyShellProvidersException
     * @throws ValidationFailedException
     */
    private function validateHostConfig($config_name, $data)
    {
        $data = $this->resolveInheritance($data, $this->hosts);

        $defaults = [
            'config_name' => $config_name, // For backwards compatibility
            'configName' => $config_name,
            'executables' => $this->getSetting('executables', []),
        ];

        if (empty($data['needs'])) {
            $data['needs'] = $this->getSetting('needs', []);
        }

        if (!$this->getSetting('disableScripts', false)) {
            if (!in_array('script', $data['needs'])) {
                $data['needs'][] = 'script';
            }
        }

        $data = $this->applyDefaults($defaults, $data);
        /**
         * @var \Phabalicious\Method\MethodInterface $method
         */
        foreach ($this->methods->all() as $method) {
            $data = $this->mergeData($method->getDefaultConfig($this, $data), $data);
        }

        // Overall validation.

        $validation_errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $validation_errors, 'host-config');
        $validation->isArray('needs', 'Please specify the needed methods as an array');
        $validation->isOneOf('type', ['prod', 'stage', 'test', 'dev']);

        // Validate data against used methods.

        $used_methods = $this->methods->getSubset($data['needs']);
        foreach ($used_methods as $method) {
            $method->validateConfig($data, $validation_errors);
        }

        // Get shell-provider.

        /** @var \Phabalicious\ShellProvider\ShellProviderInterface[] $shells */
        $shells = array_filter(array_map(function ($method) use ($data) {
            /** @var \Phabalicious\Method\MethodInterface $method */
            return $method->createShellProvider($data);
        }, $used_methods));


        if (count($shells) > 1) {
            throw new TooManyShellProvidersException('Found too many shell-providers for host-config ' . $config_name);
        } elseif (count($shells) === 0) {
            $this->logger->error('Could not find any shell provider for ' . $config_name . ', using local one.');
            $shell_provider = new LocalShellProvider($this->logger);
        } else {
            $shell_provider = reset($shells);
        }

        // Validate data against shell-provider.

        $data = $this->mergeData($shell_provider->getDefaultConfig($this, $data), $data);
        $shell_provider->validateConfig($data, $validation_errors);

        if ($validation_errors->hasErrors()) {
            throw new ValidationFailedException($validation_errors);
        }
        if ($validation_errors->getWarnings()) {
            foreach ($validation_errors->getWarnings() as $key => $warning) {
                $this->logger->warning('Found deprecated key `' . $key . '`: ' . $warning);
            }
        }

        // Create host-config and return.
        return new HostConfig($data, $shell_provider);
    }

    /**
     * @param string $config_name
     *
     * @return DockerConfig
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ValidationFailedException
     */
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

        $data = $this->validateDockerConfig($data);

        switch ($data['shellProvider']) {
            case 'local':
                $shell_provider = new LocalShellProvider($this->logger);
                break;
            case 'ssh':
                $shell_provider = new SshShellProvider($this->logger);
                break;

            default:
                $shell_provider = false;
        }
        $errors = new ValidationErrorBag();
        if (!$shell_provider) {
            $errors->addError('shellProvider', 'Unhandled shell-provider: `' . $data['shellProvider'] . '`');
        } else {
            $data = Utilities::mergeData($shell_provider->getDefaultConfig($this, $data), $data);
            $shell_provider->validateConfig($data, $errors);
        }
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }
        $data = new DockerConfig($data, $shell_provider);

        $this->cache[$cid] = $data;
        return $data;
    }

    public function getAllSettings($without = ['hosts', 'dockerHosts'])
    {
        $copy = $this->settings;
        foreach ($without as $key) {
            unset($copy[$key]);
        }
        return $copy;
    }

    public function getAllHostConfigs()
    {
        return $this->hosts;
    }

    public function addHost($host_data)
    {
        $this->hosts[$host_data['configName']] = $host_data;
    }

    public function getAllDockerConfigs()
    {
        return $this->dockerHosts;
    }

    public function getBlueprints(): BlueprintConfiguration
    {
        return $this->blueprints;
    }

    private function validateDockerConfig(array $data)
    {
        if (!empty($data['runLocally'])) {
            $data['shellProvider'] = 'local';
        }

        if (empty($data['shellProvider'])) {
            $data['shellProvider'] = 'ssh';
        }
        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'dockerHost');
        $validation->deprecate(['runLocally']);
        $validation->hasKey('shellProvider', 'The name of the shell-provider to use');
        $validation->hasKey('rootFolder', 'The rootFolder to start with');

        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        foreach ($errors->getWarnings() as $warning) {
            $this->logger->warning($warning);
        }

        return $data;
    }

    public function setOffline($offline)
    {
        $this->offlineMode = $offline;
    }

}