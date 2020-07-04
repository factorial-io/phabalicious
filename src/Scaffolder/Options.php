<?php

namespace Phabalicious\Scaffolder;

use Phabalicious\Utilities\Utilities;

class Options
{

    protected $allowOverride = false;
    
    protected $pluginRegistrationCallback = null;
    
    protected $compabilityVersion = Utilities::FALLBACK_VERSION;
    
    protected $dynamic_options = [];
    
    protected $callbacks = [];
    
    protected $skipSubfolder;
    
    protected $useCacheTokens = true;
    
    protected $variables = [];

    public function getAllowOverride()
    {
        return $this->allowOverride;
    }
    

    /**
     * @param callable $pluginRegistrationCallback
     * @return Options
     */
    public function setPluginRegistrationCallback(callable $pluginRegistrationCallback)
    {
        $this->pluginRegistrationCallback = $pluginRegistrationCallback;
        return $this;
    }

    /**
     * @param bool $allowOverride
     * @return Options
     */
    public function setAllowOverride(bool $allowOverride): Options
    {
        $this->allowOverride = $allowOverride;
        return $this;
    }

    /**
     * @return callable|null
     */
    public function getPluginRegistrationCallback()
    {
        return $this->pluginRegistrationCallback;
    }

    /**
     * @param string $compabilityVersion
     * @return Options
     */
    public function setCompabilityVersion(string $compabilityVersion): Options
    {
        $this->compabilityVersion = $compabilityVersion;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompabilityVersion(): string
    {
        return $this->compabilityVersion;
    }

    public function getDynamicOption(string $option_name)
    {
        return $this->dynamic_options[$option_name] ?? false;
    }

    /**
     * @param array $dynamic_options
     * @return Options
     */
    public function setDynamicOptions(array $dynamic_options): Options
    {
        $this->dynamic_options = $dynamic_options;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSkipSubfolder()
    {
        return $this->skipSubfolder;
    }

    /**
     * @param mixed $skipSubfolder
     * @return Options
     */
    public function setSkipSubfolder($skipSubfolder): Options
    {
        $this->skipSubfolder = $skipSubfolder;
        return $this;
    }
    
    public function addCallback($name, $callable): Options
    {
        $this->callbacks[$name] = $callable;
        return $this;
    }

    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * @param bool $useCacheTokens
     * @return Options
     */
    public function setUseCacheTokens(bool $useCacheTokens): Options
    {
        $this->useCacheTokens = $useCacheTokens;
        return $this;
    }

    /**
     * @return bool
     */
    public function useCacheTokens(): bool
    {
        return $this->useCacheTokens;
    }

    /**
     * @param $key
     * @param $value
     * @return Options
     */
    public function addVariable($key, $value): Options
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
