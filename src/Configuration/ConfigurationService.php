<?php

namespace Phabalicious\Configuration;

use Composer\Semver\Comparator;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;

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
    private $disallowDeepMergeForKeys = [];

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

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
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

        $disallow_deep_merge_for_keys = ['needs'];

        $data = $this->applyDefaults($data, $defaults, $disallow_deep_merge_for_keys);

        /**
         * @var \Phabalicious\Method\MethodInterface $method
         */

        if ($this->methods) {
            foreach ($this->methods->all() as $method) {
                $disallow_deep_merge_for_keys = array_merge(
                    $disallow_deep_merge_for_keys,
                    $method->getKeysForDisallowingDeepMerge()
                );

            }
            foreach ($this->methods->all() as $method) {
                $data = $this->applyDefaults(
                    $data,
                    $method->getGlobalSettings(),
                    $disallow_deep_merge_for_keys
                );
            }
        }

        $this->disallowDeepMergeForKeys = $disallow_deep_merge_for_keys;

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
                $p = $path . '/' . $candidate;
                $this->logger->debug('trying ' . $p);
                if (file_exists($p)) {
                    return $p;
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
        $this->fabfilePath = realpath($path);
    }

    public function getFabfilePath()
    {
        return $this->fabfilePath;
    }

    public function mergeData(array $data, array $override_data): array
    {
        return Utilities::mergeData($data, $override_data);
    }

    private function applyDefaults(array $data, array $defaults, array $disallowed_keys = [])
    {
        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            } elseif (is_array($data[$key]) && !in_array($key, $disallowed_keys)) {
                $data[$key] = $this->mergeData($defaults[$key], $data[$key]);
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
    public function resolveInheritance(array $data, $lookup, $root_folder = false): array
    {
        if (!isset($data['inheritsFrom'])) {
            return $data;
        }
        if (!$root_folder) {
            $root_folder = $this->getFabfilePath();
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
            } elseif (file_exists($root_folder . '/' . $resource)) {
                $add_data = $this->readFile($root_folder . '/' . $resource);
            }
            if ($add_data) {
                if (isset($add_data['inheritsFrom'])) {
                    $add_data = $this->resolveInheritance($add_data, $lookup, $root_folder);
                }

                // Clear inheritOnly from to be merged data, so it does not bleed into final data.
                unset($add_data['inheritOnly']);
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
        if ($this->offlineMode && !$contents) {
            $this->logger->error(
                'Could not get needed data from offline-cache for `' .
                $resource . '`, proceed with caution!'
            );
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
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
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
     * @throws ShellProviderNotFoundException
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
        $validation = new ValidationService($data, $errors, 'blueprint: `' . $identifier . '`');
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
     * @throws ShellProviderNotFoundException
     * @throws ValidationFailedException
     */
    private function validateHostConfig($config_name, $data)
    {
        $data = $this->resolveInheritance($data, $this->hosts);
        $type = isset($data['type']) ? $data['type'] : false;
        $defaults = [
            'type' => $type ? $type : 'dev',
            'config_name' => $config_name, // For backwards compatibility
            'configName' => $config_name,
            'executables' => $this->getSetting('executables', []),
            'supportsInstalls' => $type != HostType::PROD
                ? true
                : false,
            'supportsCopyFrom' => true,
            'backupBeforeDeploy' => in_array($type, [HostType::STAGE, HostType::PROD])
                ? true
                : false,
            'tmpFolder' => '/tmp',
        ];

        if (empty($data['needs'])) {
            $data['needs'] = $this->getSetting('needs', []);
        }

        if (!$this->getSetting('disableScripts', false)) {
            if (!in_array('script', $data['needs'])) {
                $data['needs'][] = 'script';
            }
        }

        $data = $this->applyDefaults($data, $defaults, $this->disallowDeepMergeForKeys);
        /**
         * @var \Phabalicious\Method\MethodInterface $method
         */
        $used_methods = $this->methods->getSubset($data['needs']);
        foreach ($used_methods as $method) {
            $data = $this->applyDefaults(
                $data,
                $method->getDefaultConfig($this, $data),
                $this->disallowDeepMergeForKeys
            );
        }

        // Overall validation.

        $validation_errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $validation_errors, 'host-config: `' . $config_name . '`');
        $validation->isArray('needs', 'Please specify the needed methods as an array');
        $validation->isOneOf('type', HostType::getAll());

        // Validate data against used methods.

        foreach ($used_methods as $method) {
            $method->validateConfig($data, $validation_errors);
        }

        // Give methods a chance to alter the config.
        foreach ($used_methods as $method) {
            $method->alterConfig($this, $data);
        }

        if (empty($data['shellProvider'])) {
            $this->logger->warning('No shell-provider definded, using `local` for now!');
            $data['shellProvider'] = 'local';
        }

        // Get shell-provider.
        $shell_provider = false;
        $shell_provider_name = $data['shellProvider'];
        if (!empty($shell_provider_name)) {
            $shell_provider = ShellProviderFactory::create($shell_provider_name, $this->logger);
        }
        if (!$shell_provider) {
            throw new ShellProviderNotFoundException(
                'Could not find any shell provider `' .
                $shell_provider_name.
                '` for `' . $config_name .
                '`!'
            );
        }

        // Validate data against shell-provider.

        $data = $this->mergeData($shell_provider->getDefaultConfig($this, $data), $data);
        $shell_provider->validateConfig($data, $validation_errors);

        if ($validation_errors->hasErrors()) {
            throw new ValidationFailedException($validation_errors);
        }
        if ($validation_errors->getWarnings()) {
            foreach ($validation_errors->getWarnings() as $key => $warning) {
                $this->logger->warning('Found deprecated key in `' . $config_name .'`, `' . $key . '`: ' . $warning);
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
        if (!empty($data['inheritOnly'])) {
            return $data;
        }

        $data = $this->validateDockerConfig($data, $config_name);
        $shell_provider = ShellProviderFactory::create($data['shellProvider'], $this->logger);


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

    private function validateDockerConfig(array $data, $config_name)
    {
        $data['configName'] = 'dockerHosts.' . $config_name;

        if (!empty($data['runLocally'])) {
            $data['shellProvider'] = 'local';
        }

        if (empty($data['shellProvider'])) {
            $data['shellProvider'] = 'ssh';
        }
        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $errors, 'dockerHost: `' . $config_name . '`');
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