<?php

namespace Phabalicious\Configuration;

use Composer\Semver\Comparator;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;

class ConfigurationService
{
    const MAX_FILECACHE_LIFETIME = 60 * 60;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $application;

    /**
     * @var MethodFactory|null
     */
    private $methods;

    private $fabfilePath;
    private $fabfileLocation;

    private $dockerHosts;
    private $hosts;
    private $settings;

    /** @var array */
    private $cache;

    /** @var BlueprintConfiguration */
    private $blueprints;
    private $offlineMode = false;
    private $skipCache = false;
    private $disallowDeepMergeForKeys = [];

  /**
   * @var bool
   */
    protected $strictRemoteHandling = false;

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
     * @throws BlueprintTemplateNotFoundException
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
        $this->fabfileLocation = realpath($fabfile);

        $data = $this->resolveInheritance($data, $data);

        $local_override_file = $this->findFabfilePath(['fabfile.local.yaml', 'fabfile.local.yml']);
        if (!$local_override_file) {
            $local_override_file = $this->findFabfilePath(
                ['.fabfile.local.yaml', '.fabfile.local.yml'],
                $_SERVER['HOME']
            );
        }
        if ($local_override_file) {
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
            $errors = new ValidationErrorBag();
            foreach ($this->methods->all() as $method) {
                $method->validateGlobalSettings($data, $errors);
            }
            if ($errors->hasErrors()) {
                throw new ValidationFailedException($errors);
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
        while ($depth <= 5) {
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
     * Check requires of data.
     *
     * @param array $data
     * @param $file
     * @throws MismatchedVersionException
     */
    protected function checkRequires(array $data, $file)
    {

        if ($data && isset($data['requires'])) {
            $required_version = $data['requires'];
            $app_version = $this->application->getVersion();
            $this->getLogger()->debug(sprintf("required %s in %s, app has %s", $required_version, $file, $app_version));
            if (Comparator::greaterThan($required_version, $app_version)) {
                throw new MismatchedVersionException(
                    sprintf(
                        'Could not read from %s because of version mismatch. %s is required, current app is %s',
                        $file,
                        $required_version,
                        $app_version
                    )
                );
            }
        }
    }

    /**
     * @param string $file
     *
     * @return mixed
     * @throws MismatchedVersionException
     */
    protected function readFile(string $file)
    {
        $cid = 'yaml:' . $file;
        if (isset($this->cache[$cid])) {
            return $this->cache[$cid];
        }

        $this->logger->info(sprintf('Read data from `%s`', $file));
        $data = Yaml::parseFile($file);
        $ext = '.' . pathinfo($file, PATHINFO_EXTENSION);
        $override_file = str_replace($ext, '.override' . $ext, $file);
        $this->logger->debug(sprintf('Trying to read data from override `%s`', $override_file));

        if (file_exists($override_file)) {
            $data = Utilities::mergeData($data, Yaml::parseFile($override_file));
        }
        $env_file = dirname($file) . '/.env';
        if (file_exists($env_file)) {
            $this->logger->info(sprintf('Reading .env from %s', $env_file));
            $dotenv = new Dotenv();
            $contents = file_get_contents($env_file);
            $envvars = $dotenv->parse($contents);
            if (is_array($envvars)) {
                $data['environment'] = Utilities::mergeData($envvars, $data['environment'] ?? []);
            }
        }

        $this->checkRequires($data, $file);


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

    public function getFabfileLocation()
    {
        return $this->fabfileLocation;
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
     * @param array $lookup
     *
     * @param bool $root_folder
     * @param array $stack
     * @return array
     *
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     */
    public function resolveInheritance(array $data, $lookup, $root_folder = false, $stack = []): array
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
            if (in_array($resource, $stack)) {
                throw new \InvalidArgumentException(sprintf(
                    "Possible recursion in inheritsFrom detected! `%s `in [%s]",
                    $resource,
                    implode(', ', $stack)
                ));
            }
            $add_data = false;
            if (isset($lookup[$resource])) {
                $add_data = $lookup[$resource];
            } elseif (strpos($resource, 'http') !== false) {
                $add_data = Yaml::parse($this->readHttpResource($resource));
                if ($add_data) {
                    $this->checkRequires($add_data, $resource);
                }
            } elseif (file_exists($resource)) {
                $add_data = $this->readFile($resource);
                if ($add_data) {
                    $this->checkRequires($add_data, $resource);
                }
            } elseif (file_exists($root_folder . '/' . $resource)) {
                $add_data = $this->readFile($root_folder . '/' . $resource);
            } else {
                throw new FabfileNotReadableException(sprintf(
                    'Could not resolve inheritance from `inheritsFrom: %s`',
                    $resource
                ));
            }
            if (!empty($add_data['deprecated'])) {
                $this->logger->warning(sprintf(
                    'Inherited data from `%s` is deprecated: %s',
                    $resource,
                    $add_data['deprecated']
                ));
                unset($add_data['deprecated']);
            }
            if ($add_data) {
                if (isset($add_data['inheritsFrom'])) {
                    $stack[] = $resource;
                    $add_data = $this->resolveInheritance($add_data, $lookup, $root_folder, $stack);
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
        $cache_file = getenv("HOME")
            . '/.phabalicious/' . md5($resource)
            . '.' . pathinfo($resource, PATHINFO_EXTENSION);

        // Check for cached version, maximum age 60 minutes.
        if (!$this->skipCache &&
            file_exists($cache_file) &&
            time()-filemtime($cache_file) < self::MAX_FILECACHE_LIFETIME
        ) {
            $this->logger->info('Using cached version for `' . $resource .'`');
            $contents = file_get_contents($cache_file);
            if (!empty($contents)) {
                $this->cache[$cid] = $contents;

                return $contents;
            }
        }
        if (!$this->offlineMode) {
            try {
                $this->logger->info(sprintf('Read remote file from `%s`', $resource));
                $url = parse_url($resource);
                $url['path'] = urlencode($url['path']);
                $url['path'] = str_replace('%2F', '/', $url['path']);
                $resource =  http_build_url($url);
                set_error_handler(
                    function ($severity, $message) {
                        throw new FabfileNotReadableException($message);
                    }
                );

                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => [
                            'User-Agent: phabalicious  (factorial-io/phabalicious)' . ' (PHP)'
                        ]
                    ]
                ];

                $context = stream_context_create($opts);
                $contents = file_get_contents($resource, false, $context);
            } catch (\Exception $e) {
                $this->logger->warning('Could not load resource from `' . $resource . '`: ' . $e->getMessage());
                $contents = false;
            } finally {
                restore_error_handler();
            }
        }

        if (!is_dir(dirname($cache_file))) {
            mkdir(dirname($cache_file), 0777, true);
        }

        if (empty($contents) && file_exists($cache_file)) {
            $this->logger->info('Using cached version for `' . $resource .'`');
            $contents = file_get_contents($cache_file);
        } elseif (!empty($contents)) {
            file_put_contents($cache_file, $contents);
        }
        if (!$contents) {
            $message = sprintf('Could not get needed data from `%s`!', $resource);
            if ($this->isStrictRemoteHandling()) {
                throw new FabfileNotReadableException($message);
            } else {
                $this->logger->error($message);
            }
        }
        $this->cache[$cid] = $contents;

        return $contents;
    }

  /**
   * @param string $config_name
   *
   * @return HostConfig
   * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
   * @throws FabfileNotReadableException
   * @throws \Phabalicious\Exception\MismatchedVersionException
   * @throws \Phabalicious\Exception\MissingHostConfigException
   * @throws \Phabalicious\Exception\ShellProviderNotFoundException
   * @throws \Phabalicious\Exception\ValidationFailedException
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

        if (isset($data['inheritFromBlueprint'])) {
            $data = $this->inheritFromBlueprint($config_name, $data);
        }
        $data = $this->validateHostConfig($config_name, $data);

        $this->cache[$cid] = $data;
        return $data;
    }

  /**
   * @param string $blueprint
   * @param string $identifier
   *
   * @return HostConfig
   * @throws MismatchedVersionException
   * @throws ShellProviderNotFoundException
   * @throws ValidationFailedException
   * @throws BlueprintTemplateNotFoundException
   * @throws FabfileNotReadableException
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

        $this->cache['host:' . $data['configName']] = $data;
        $this->cache[$cid] = $data;
        return $data;
    }

  /**
   * @param string $config_name
   * @param array $data
   *
   * @return HostConfig
   * @throws MismatchedVersionException
   * @throws ShellProviderNotFoundException
   * @throws ValidationFailedException
   * @throws FabfileNotReadableException
   */
    private function validateHostConfig($config_name, $data)
    {
        $data = $this->resolveInheritance($data, $this->hosts);
        $type = isset($data['type']) ? $data['type'] : false;
        $type = HostType::convertLegacyTypes($type);
        if (!empty($type)) {
            $data['type'] = $type;
        }

        $defaults = [
            'type' => $type ? $type : 'dev',
            'config_name' => $config_name, // For backwards compatibility
            'configName' => $config_name,
            'executables' => $this->getSetting('executables', []),
            'supportsInstalls' => $type != HostType::PROD,
            'supportsCopyFrom' => true,
            'backupBeforeDeploy' => in_array($type, [HostType::STAGE, HostType::PROD])
                ? true
                : false,
            'tmpFolder' => '/tmp',
            'rootFolder' => $this->getFabfilePath(),
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
        return new HostConfig($data, $shell_provider, $this);
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
        $data = $this->applyDefaults($data, [
            'tmpFolder' => '/tmp',
        ]);
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
        $data = new DockerConfig($data, $shell_provider, $this);

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
        $validation->hasKey('tmpFolder', 'The rootFolder to use');
        if (!empty($data['rootFolder']) && $data['rootFolder'][0] === '.') {
            $data['rootFolder'] = realpath($this->getFabfilePath() . '/' . $data['rootFolder']);
        }

        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        foreach ($errors->getWarnings() as $warning) {
            $this->logger->warning($warning);
        }

        // Remove trailing slashes.
        $data['rootFolder'] = rtrim($data['rootFolder'], '/');

        return $data;
    }

    public function setOffline($offline): ConfigurationService
    {
        $this->offlineMode = $offline;
        return $this;
    }

    public function isOffline()
    {
        return $this->offlineMode;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $config_name
     * @param array $data
     * @return array
     * @throws BlueprintTemplateNotFoundException
     * @throws FabfileNotReadableException
     * @throws MismatchedVersionException
     * @throws ShellProviderNotFoundException
     * @throws ValidationFailedException
     */
    protected function inheritFromBlueprint(string $config_name, $data): array
    {
        $errors = new ValidationErrorBag();
        $validation = new ValidationService($data['inheritFromBlueprint'], $errors, 'inheritFromBlueprint');
        $validation->hasKeys([
            'config' => 'The inheritFromBlueprint needs to know which blueprint config to use.',
            'variant' => 'The inheritFromBlueprint needs a variant',
        ]);
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }
        $add_data = $this->getHostConfigFromBlueprint(
            $data['inheritFromBlueprint']['config'],
            $data['inheritFromBlueprint']['variant']
        );
        unset($data['inheritFromBlueprint']);

        $data = $this->mergeData($add_data->raw(), $data);
        $data['configName'] = $config_name;

        return $data;
    }

    public function hasHostConfig($configName)
    {
        return !empty($this->hosts[$configName]);
    }

    /**
     * @param bool $skipCache
     * @return ConfigurationService
     */
    public function setSkipCache($skipCache): ConfigurationService
    {
        $this->skipCache = $skipCache;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSkipCache(): bool
    {
        return $this->skipCache;
    }

    public function setStrictRemoteHandling(bool $flag)
    {
        $this->strictRemoteHandling = $flag;
    }

  /**
   * @return bool
   */
    public function isStrictRemoteHandling(): bool
    {
        return $this->strictRemoteHandling;
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {

        $needs = $host_config['needs'];
        foreach ($this->getMethodFactory()->getSubset($needs) as $method) {
            if ($method->isRunningAppRequired($host_config, $context, $task)) {
                return true;
            }
        }

        return false;
    }

    public function findScript(HostConfig $host_config, $script_name)
    {
        if (!empty($host_config['scripts'][$script_name])) {
            return $host_config['scripts'][$script_name];
        }
        return $this->getSetting('scripts.' . $script_name, false);
    }
}
