<?php

namespace Phabalicious\Configuration;

use Composer\Semver\Comparator;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Configuration\Storage\Store;
use Phabalicious\Exception\BlueprintTemplateNotFoundException;
use Phabalicious\Exception\FabfileNotFoundException;
use Phabalicious\Exception\FabfileNotReadableException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\MissingHostConfigException;
use Phabalicious\Exception\ShellProviderNotFoundException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Exception\YamlParseException;
use Phabalicious\Method\MethodFactory;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\Utilities\Logger;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Utilities\PasswordManagerInterface;
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

    const DISCARD_DEPRECATED_PROPERTIES = false;

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
    /** @var Node */
    private $settings;

    /** @var array */
    private $cache;

    /** @var BlueprintConfiguration */
    private $blueprints;
    private $offlineMode = false;
    private $skipCache = false;
    private $disallowDeepMergeForKeys = [];

    /** @var PasswordManagerInterface */
    private $passwordManager = null;

    private $inheritanceBaseUrl = false;

  /**
   * @var bool
   */
    protected $strictRemoteHandling = false;

    /**
     * @var ValidationErrorBag
     */
    protected $deprecationMessages = null;

    public function __construct(Application $application, LoggerInterface $logger)
    {
        $this->settings = new Node([], 'defaults');
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
        if (!$this->settings->isEmpty()) {
            return true;
        }

        $fabfile = !empty($override) ? $override : $this->findFabfilePath([
            'fabfile.yml',
            '.fabfile.yml',
            'fabfile.yaml',
            '.fabfile.yaml',
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
        Store::setProtectedProperties($data, 'protectedProperties');

        $local_override_file = $this->findFabfilePath(['fabfile.local.yaml', 'fabfile.local.yml']);
        if (!$local_override_file) {
            $local_override_file = $this->findFabfilePath(
                ['.fabfile.local.yaml', '.fabfile.local.yml'],
                $_SERVER['HOME']
            );
        }
        if ($local_override_file) {
            $override_data = $this->readFile($local_override_file);
            $data = $data->merge($override_data);
        }

        Store::resetProtectedProperties();

        $defaults = [
            'needs' => ['git', 'ssh', 'drush7', 'files'],
            'common' => [],
            'hosts' => [],
            'dockerHosts' => [],
        ];

        $disallow_deep_merge_for_keys = ['needs'];

        $data = $this->applyDefaults($data, new Node($defaults, 'global defaults'), $disallow_deep_merge_for_keys);

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
                    $method->getGlobalSettings($this),
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
        $this->reportDeprecations($fabfile);

        $this->hosts = $this->settings->get('hosts', []);
        $this->dockerHosts = $this->settings->get('dockerHosts', []);

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
     * @param Node $data
     * @param $file
     * @throws MismatchedVersionException
     */
    protected function checkRequires(Node $data, $file)
    {

        if ($data->has('requires')) {
            $required_version = $data['requires'];
            // Alpha or beta versions act like released versions for requires.
            $app_version = Utilities::getNextStableVersion($this->application->getVersion());
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
        try {
            $data = Node::parseYamlFile($file);
        } catch (\Exception $e) {
            throw new YamlParseException("Could not parse file `$file`", 0, $e);
        }
        $ext = '.' . pathinfo($file, PATHINFO_EXTENSION);
        $override_file = str_replace($ext, '.override' . $ext, $file);
        $this->logger->debug(sprintf('Trying to read data from override `%s`', $override_file));

        if (file_exists($override_file)) {
            $data->merge(Node::parseYamlFile($override_file));
        }
        $env_file = dirname($file) . '/.env';
        if (file_exists($env_file)) {
            $this->logger->info(sprintf('Reading .env from %s', $env_file));
            $dotenv = new Dotenv();
            $contents = file_get_contents($env_file);
            $envvars = $dotenv->parse($contents);
            if (is_array($envvars)) {
                $environment = $data->getOrCreate('environment', []);
                $environment->merge(new Node($envvars, $env_file));
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

    public function mergeData(
        Node $data,
        Node $override_data,
        $protected_properties_key = 'protectedProperties'
    ): Node {
        $properties_to_restore = [];
        if ($protected_properties = $data[$protected_properties_key] ?? false) {
            if (!is_array($protected_properties)) {
                $protected_properties = [ $protected_properties ];
            }
            foreach ($protected_properties as $prop) {
                $properties_to_restore[$prop] = Utilities::getProperty($data, $prop);
            }
        }

        $data = Node::mergeData($data, $override_data);

        foreach ($properties_to_restore as $prop => $value) {
            $data->setProperty($prop, $value);
        }
        return $data;
    }

    private function applyDefaults(Node $data, Node $defaults, array $disallowed_keys = []): Node
    {
        foreach ($defaults as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = $value;
            } elseif ($data->get($key)->isArray() && !in_array($key, $disallowed_keys)) {
                $data[$key] = $data->get($key)->baseOntop($defaults->get($key));
            }
        }
        return $data;
    }

    /**
     * Resolve relative includes to absolute paths/urls.
     *
     * @param \Phabalicious\Configuration\Storage\Node $data
     * @param  $base_url
     *
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     */
    public function resolveRelativeInheritanceRefs(
        Node $data,
        $base_url,
        string $inherit_key = "inheritsFrom"
    ) {
        if ($base_url && substr($base_url, -1) !== '/') {
            $base_url .= '/';
        }
        /** @var Node $node */
        foreach ($data->findNodes($inherit_key, 1) as $node) {
            if (!$node->isArray()) {
                $node->ensureArray();
            }
            foreach ($node as $child) {
                $item = $child->getValue();

                // Skip urls and absolute paths:
                if ($item[0] === '/' || Utilities::isHttpUrl($item) || Utilities::isPharUrl($item)) {
                    continue;
                }

                $parent = dirname($child->getSource()->getSource());
                if (substr($parent, -1) !== '/') {
                    $parent .= '/';
                }
                $file_ext = pathinfo($item, PATHINFO_EXTENSION);

                if ($item[0] === '.') {
                    $item = Utilities::resolveRelativePaths($parent . $item);
                } elseif ($item[0] === '@') {
                    if (!$base_url) {
                        throw new FabfileNotReadableException(
                            "No base url provided, can't resolve relative references!"
                        );
                    }
                    $item = Utilities::resolveRelativePaths($base_url . '.' . substr($item, 1));
                } elseif (in_array($file_ext, ['yml', 'yaml'], true)) {
                    $item = Utilities::resolveRelativePaths($parent . './' . $item);
                }
                $child->setValue($item);
            }
        }
    }

    /**
     * Resolve inheritance for given data.
     *
     * @param \Phabalicious\Configuration\Storage\Node $data
     * @param \Phabalicious\Configuration\Storage\Node $lookup
     *
     * @param string|null $root_folder
     * @param array $stack
     * @param string $inherit_key
     *
     * @return \Phabalicious\Configuration\Storage\Node
     *
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     */
    public function resolveInheritance(
        Node $data,
        Node $lookup,
        ?string $root_folder = null,
        array $stack = [],
        string $inherit_key = "inheritsFrom"
    ): Node {
        if (empty($stack)) {
            $this->deprecationMessages = new ValidationErrorBag();
        }
        if (!$data->has($inherit_key)) {
            return $data;
        }

        if (!$root_folder) {
            $root_folder = $this->getFabfilePath();
        }

        $baseUrl = $lookup['inheritanceBaseUrl'] ?? $this->getInheritanceBaseUrl();
        if ($baseUrl && $baseUrl[0] == '.') {
            $fullpathBaseUrl = realpath($baseUrl);
            if (!$fullpathBaseUrl) {
                throw new \RuntimeException(sprintf('Could not resolve/ find base url: `%s`', $baseUrl));
            }
            $baseUrl = $fullpathBaseUrl;
        }
        $this->resolveRelativeInheritanceRefs($data, $baseUrl);

        $inheritsFrom = $data->get($inherit_key);

        unset($data[$inherit_key]);

        foreach ($inheritsFrom->iterateBackwardsOverValues() as $resource) {
            if (in_array($resource, $stack)) {
                throw new \InvalidArgumentException(sprintf(
                    "Possible recursion in inheritsFrom detected! `%s `in [%s]",
                    $resource,
                    implode(', ', $stack)
                ));
            }
            $add_data = false;
            if ($lookup->has($resource)) {
                $add_data = $lookup->get($resource);
            } elseif (Utilities::isHttpUrl($resource)) {
                $content = $this->readHttpResource($resource);
                if ($content) {
                    $add_data = new Node(Yaml::parse($content), $resource);
                    $this->resolveRelativeInheritanceRefs($add_data, $baseUrl);
                    $this->checkRequires($add_data, $resource);
                }
            } elseif (file_exists($resource)) {
                $add_data = $this->readFile($resource);
                if ($add_data) {
                    $this->resolveRelativeInheritanceRefs($add_data, $baseUrl);
                    $this->checkRequires($add_data, $resource);
                }
            } elseif (file_exists($root_folder . '/' . $resource)) {
                $add_data = $this->readFile($root_folder . '/' . $resource);
                if ($add_data) {
                    $this->resolveRelativeInheritanceRefs($add_data, $baseUrl);
                    $this->checkRequires($add_data, $resource);
                }
            } else {
                throw new FabfileNotReadableException(sprintf(
                    "Could not resolve inheritance from `inheritsFrom: %s`! \n\nPossible values:\n%s",
                    $resource,
                    '- ' . implode("\n- ", array_keys($lookup->asArray()))
                ));
            }
            if ($add_data && $add_data->has('deprecated')) {
                $this->deprecationMessages->addWarning(
                    $resource,
                    sprintf('Inherited data from `%s` is deprecated: %s', $resource, $add_data['deprecated'])
                );
                unset($add_data['deprecated']);
            }
            if ($add_data) {
                if ($add_data->has($inherit_key)) {
                    $stack[] = $resource;
                    $add_data = $this->resolveInheritance($add_data, $lookup, $root_folder, $stack);
                    array_pop($stack);
                }

                // Clear inheritOnly from to be merged data, so it does not bleed into final data.
                unset($add_data['inheritOnly']);
                $data = $data->baseOnTop($add_data);
            }
        }

        return $data;
    }

    public function getSetting(string $key, $default_value = null)
    {
        return $this->settings ? $this->settings->getProperty($key, $default_value) : $default_value;
    }

    public function readHttpResource(string $resource)
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
                $url = Utilities::parseUrl($resource);
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
                            'User-Agent: phabalicious  (factorial-io/phabalicious)' . ' (PHP)',
                        ],
                    ],
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

        $data = Node::clone($this->hosts->get($config_name));

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
    public function getHostConfigFromBlueprint(string $blueprint, string $identifier, $skip_host_validation = false)
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
        if (!$skip_host_validation) {
            $data = $this->validateHostConfig($data['configName'], $data);
        }

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
    private function validateHostConfig($config_name, Node $data)
    {
        $data = $this->resolveInheritance($data, $this->hosts);
        $this->reportDeprecations(sprintf('hosts.%s', $config_name));

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
            'supportsInstalls' => $type !== HostType::PROD,
            'supportsCopyFrom' => true,
            'backupBeforeDeploy' => in_array($type, [HostType::STAGE, HostType::PROD]),
            'tmpFolder' => '/tmp',
            'rootFolder' => $this->getFabfilePath(),
        ];

        if (!$data->has('needs')) {
            $data->set('needs', Node::clone($this->settings->get('needs', [])));
        }
        $data->get('needs')->ensureArray();

        if (!$this->getSetting('disableScripts', false)) {
            if (!in_array('script', $data->get('needs')->asArray())) {
                $data->get('needs')->push('script');
            }
        }
        if ($data->has('additionalNeeds')) {
            $additional_needs = $data->get('additionalNeeds');
            $additional_needs->ensureArray();
            foreach ($additional_needs as $item) {
                $data->get('needs')->push($item);
            }
        }

        $data = $this->applyDefaults($data, new Node($defaults, 'host defaults'), $this->disallowDeepMergeForKeys);
        /**
         * @var \Phabalicious\Method\MethodInterface $method
         */
        $used_methods = $this->methods->getSubset($data->get('needs')->asArray());

        // Give the referenced methods a chance to declare dependencies to
        // other methods.
        $gathered_methods = [];
        foreach ($used_methods as $method) {
            $dependencies = $method->getMethodDependencies($this->getMethodFactory(), $data);
            $gathered_methods = array_merge(array_values($gathered_methods), $dependencies, [$method->getName()]);
        }
        $data['needs'] = $gathered_methods;
        $used_methods = $this->methods->getSubset($data['needs']);

        // Overall validation.
        $validation_errors = new ValidationErrorBag();
        $validation = new ValidationService($data, $validation_errors, 'host-config: `' . $config_name . '`');

        // Apply defaults and handle deprecations

        foreach ($used_methods as $method) {
            if (!empty($deprecation_mapping = $method->getDeprecationMapping())) {
                $this->mapDeprecatedConfig($data, $deprecation_mapping);
                foreach ($deprecation_mapping as $old => $new) {
                    $validation->deprecate([
                        $old => sprintf("Please use new format: `%s`", $new),
                    ]);
                    if (self::DISCARD_DEPRECATED_PROPERTIES) {
                        unset($data[$old]);
                    }
                }
            }

            foreach ($method->getDeprecatedValuesMapping() as $mapping) {
                if ($n = $data->find($mapping->getKey())) {
                    if ($mapping->apply($n)) {
                        $validation->deprecate([
                             $mapping->getKey() => $mapping->getDeprecationMessage(),
                        ]);
                    }
                }
            }
            $data = $this->applyDefaults(
                $data,
                $method->getDefaultConfig($this, $data),
                $this->disallowDeepMergeForKeys
            );
        }


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

        $data = $data->baseOntop($shell_provider->getDefaultConfig($this, $data));
        $shell_provider->validateConfig($data, $validation_errors);

        if ($validation_errors->hasErrors()) {
            throw new ValidationFailedException($validation_errors);
        }
        if ($validation_errors->getWarnings()) {
            foreach ($validation_errors->getWarnings() as $key => $warning) {
                $this->logger->warning(sprintf(
                    'Found deprecated key `%s` in `%s`: %s',
                    $key,
                    $config_name,
                    $warning
                ));
            }
        }

        // Populate info if available:
        if (isset($data['info'])) {
            $replacements = Utilities::expandVariables([
                'globals' => Utilities::getGlobalReplacements($this),
                'host' => $data->asArray(),
                'settings' => $this->getAllSettings()
            ]);
            $data['info'] = Utilities::expandStrings($data['info'], $replacements);
        }


        // Create host-config and return.
        $host_config = new HostConfig($data, $shell_provider, $this);

        if (!$this->getPasswordManager()) {
            throw new \RuntimeException('No password manager found!');
        }

        return $host_config;
    }

    /**
     * @param string $config_name
     *
     * @return DockerConfig
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ValidationFailedException
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

        $data = Node::clone($this->dockerHosts->get($config_name));
        $data = $this->resolveInheritance($data, $this->dockerHosts);
        $this->reportDeprecations(sprintf('dockerHosts.%s', $config_name));

        $data = $this->applyDefaults($data, new Node([
            'tmpFolder' => '/tmp',
        ], 'docker defaults'));
        if (!empty($data['inheritOnly'])) {
            return $data;
        }

        $data = $this->validateDockerConfig($data, $config_name);
        $shell_provider = ShellProviderFactory::create($data['shellProvider'], $this->logger);


        $errors = new ValidationErrorBag();
        if (!$shell_provider) {
            $errors->addError('shellProvider', 'Unhandled shell-provider: `' . $data['shellProvider'] . '`');
        } else {
            $data = Node::mergeData($shell_provider->getDefaultConfig($this, $data), $data);
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
        $copy = $this->settings->asArray();
        foreach ($without as $key) {
            unset($copy[$key]);
        }
        return $copy;
    }

    public function getRawSettings(): Node
    {
        return $this->settings;
    }

    public function getAllHostConfigs(): Node
    {
        return $this->hosts;
    }

    public function addHost($host_data)
    {
        if (empty($this->hosts)) {
            $this->hosts = new Node([], 'configuration');
        }
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

    private function validateDockerConfig(Node $data, $config_name): Node
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
    protected function inheritFromBlueprint(string $config_name, Node $data): Node
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
            $data['inheritFromBlueprint']['variant'],
            true
        );
        unset($data['inheritFromBlueprint']);

        $data = $data->baseOntop($add_data);
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
        $result = false;
        foreach ($this->getMethodFactory()->getSubset($needs) as $method) {
            if ($method->isRunningAppRequired($host_config, $context, $task)) {
                $result = true;
                break;
            }
        }

        $this->logger->debug("$task requires running app? " . ($result ? "YES" : "NO"));
        return $result;
    }

    public function findScript(HostConfig $host_config, $script_name)
    {
        if (!empty($host_config['scripts'][$script_name])) {
            return $host_config['scripts'][$script_name];
        }
        return $this->getSetting('scripts.' . $script_name, false);
    }

    /**
     * @return PasswordManagerInterface
     */
    public function getPasswordManager(): ?PasswordManagerInterface
    {
        if (!$this->passwordManager) {
            $this->setPasswordManager(new PasswordManager());
        }
        return $this->passwordManager;
    }

    /**
     * @param PasswordManagerInterface $passwordManager
     *
     * @return ConfigurationService
     */
    public function setPasswordManager(PasswordManagerInterface $passwordManager): ConfigurationService
    {
        $this->passwordManager = $passwordManager;
        if ($this->logger instanceof Logger) {
            $this->logger->setPasswordManager($passwordManager);
        }
        return $this;
    }

    /**
     * Get the base url for inheritance, scaffolds.
     *
     * @return bool|string
     */
    public function getInheritanceBaseUrl()
    {
        return !empty($this->inheritanceBaseUrl)
            ? $this->inheritanceBaseUrl
            : $this->getSetting("inheritanceBaseUrl", false);
    }

    /**
     * @param string|bool $inheritanceBaseUrl
     *
     * @return ConfigurationService
     */
    public function setInheritanceBaseUrl(string $inheritanceBaseUrl): ConfigurationService
    {
        $this->inheritanceBaseUrl = $inheritanceBaseUrl;
        return $this;
    }

    public function setSetting(string $key, $value)
    {
        $this->settings->set(
            $key,
            $value instanceof Node ? $value : new Node($value, 'code')
        );
    }


    public function reportDeprecations(string $source): bool
    {
        if (!$this->deprecationMessages || !$this->deprecationMessages->hasWarnings()) {
            return false;
        }
        $messages = [];
        $messages[] = sprintf('`%s` contains inherited, but deprecated configuration!', $source);
        $messages[] = "";
        foreach ($this->deprecationMessages->getWarnings() as $msg) {
            $messages[] = $msg;
        }
        $this->logger->warning(implode("\n", $messages));

        return true;
    }

    public function getData(): Node
    {
        return $this->settings;
    }

    private function mapDeprecatedConfig(Node $data, array $mapping)
    {
        foreach ($mapping as $deprecated => $key) {
            if (!is_null($deprecated_value = $data->getProperty($deprecated))) {
                $existing_value = $data->find($key);
                if (is_null($existing_value)) {
                    $data->setProperty($key, $deprecated_value);
                }
            }
        }
    }
}
