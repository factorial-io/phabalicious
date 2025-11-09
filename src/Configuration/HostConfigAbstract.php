<?php

namespace Phabalicious\Configuration;

use ArrayAccess;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\BaseMethod;
use Phabalicious\ShellProvider\ShellProviderInterface;

abstract class HostConfigAbstract implements \ArrayAccess
{
    /** @var Node */
    protected $data;

    /** @var ShellProviderInterface */
    protected $shell;

    /** @var ConfigurationService */
    protected $configurationService;

    /** @var array|bool */
    protected $publicUrls = false;

    protected $category;

    public function __construct($data, ShellProviderInterface $shell, ConfigurationService $parent)
    {
        $this->configurationService = $parent;
        $this->data = $data instanceof Node ? $data : new Node($data, 'unknown');
        $this->shell = $shell;
        $this->category = HostConfigurationCategory::getOrCreate(
            $data['info']['category'] ?? ['id' => 'unknown', 'label' => 'Unknown category']
        );

        $shell->setHostConfig($this);
    }

    public function shell(): ShellProviderInterface
    {
        return $this->shell;
    }

    public function getConfigurationService(): ConfigurationService
    {
        return $this->configurationService;
    }

    public function getData(): Node
    {
        return $this->data;
    }

    public function asArray(): array
    {
        return $this->getData()->asArray();
    }

    /**
     * Whether a offset exists.
     *
     * @see https://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     *                      </p>
     *
     * @return bool true on success or false on failure.
     *              </p>
     *              <p>
     *              The return value will be casted to boolean if non-boolean was returned.
     *
     * @since 5.0.0
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function get($key, $default = null)
    {
        if (empty($this->data[$key])) {
            return $default;
        }

        return $this->data[$key];
    }

    public function isType(string $type)
    {
        return $this['type'] == $type;
    }

    public function isMethodSupported(BaseMethod $method): bool
    {
        foreach ($this->get('needs') as $need) {
            if ($method->supports($need)) {
                return true;
            }
        }

        return false;
    }

    public function setChild(string $parent, string $child, $value)
    {
        $node = $this->data->get($parent);
        $node->set($child, new Node($value, $node->getSource()));
    }

    public function setProperty(string $key, string $value)
    {
        $this->data->setProperty($key, $value);
    }

    public function getProperty(string $property, $default = null)
    {
        return $this->data->getProperty($property, $default);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getConfigName()
    {
        return $this->data['configName'] ?? 'unknown config name';
    }

    public function getPublicUrls()
    {
        if ($this->publicUrls) {
            return $this->publicUrls;
        }
        $urls = false;
        foreach (['publicUrls', 'publicUrl'] as $key) {
            if (!$urls) {
                $urls = $this->data['info'][$key] ?? false;
            }
        }
        if (!$urls) {
            return [];
        }
        $this->publicUrls = is_array($urls) ? $urls : [$urls];

        return $this->publicUrls;
    }

    public function getMainPublicUrl(): ?string
    {
        $urls = $this->getPublicUrls();

        return $urls ? $urls[0] : false;
    }

    public function getDescription(): string
    {
        return $this->data['info']['description'] ?? '';
    }

    public function getLabel()
    {
        return $this->getMainPublicUrl()
            ? sprintf('%s [%s]', $this->getConfigName(), $this->getMainPublicUrl())
            : $this->getConfigName();
    }

    public function getCategory(): HostConfigurationCategory
    {
        return $this->category;
    }

    public function getDockerConfig(): ?DockerConfig
    {
        $docker_config_name = $this->getProperty('docker.configuration', null);

        return $docker_config_name ? $this->getConfigurationService()->getDockerConfig($docker_config_name) : null;
    }
}
