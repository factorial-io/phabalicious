<?php

namespace Phabalicious\Scaffolder;

use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;

class Options extends CallbackOptions
{
    protected $allowOverride = false;

    protected $pluginRegistrationCallback;

    protected $compabilityVersion = Utilities::FALLBACK_VERSION;

    protected $dynamicOptions = [];

    protected $skipSubfolder = false;

    protected $useCacheTokens = true;

    protected $dryRun = false;

    protected $quiet = false;

    protected $variables = [];

    protected $baseUrl = false;

    /** @var ShellProviderInterface */
    protected $shell;

    /** @var Node */
    protected $definition;

    protected $rootPath;

    public function getAllowOverride(): bool
    {
        return $this->allowOverride;
    }

    public function setPluginRegistrationCallback(callable $pluginRegistrationCallback): Options
    {
        $this->pluginRegistrationCallback = $pluginRegistrationCallback;

        return $this;
    }

    public function setAllowOverride(bool $allowOverride): Options
    {
        $this->allowOverride = $allowOverride;

        return $this;
    }

    public function getPluginRegistrationCallback(): ?callable
    {
        return $this->pluginRegistrationCallback;
    }

    public function setCompabilityVersion(string $compabilityVersion): Options
    {
        $this->compabilityVersion = $compabilityVersion;

        return $this;
    }

    public function getCompabilityVersion(): string
    {
        return $this->compabilityVersion;
    }

    public function getDynamicOption(string $option_name)
    {
        return $this->dynamicOptions[$option_name] ?? false;
    }

    public function setDynamicOptions(array $dynamic_options): Options
    {
        $this->dynamicOptions = $dynamic_options;

        return $this;
    }

    public function getSkipSubfolder(): bool
    {
        return $this->skipSubfolder;
    }

    public function setSkipSubfolder($skipSubfolder): Options
    {
        $this->skipSubfolder = $skipSubfolder;

        return $this;
    }

    public function setUseCacheTokens(bool $useCacheTokens): Options
    {
        $this->useCacheTokens = $useCacheTokens;

        return $this;
    }

    public function useCacheTokens(): bool
    {
        return $this->useCacheTokens;
    }

    /**
     * @param $key
     *               The key name of the variable
     * @param $value
     *               The value of the variable
     */
    public function addVariable($key, $value): Options
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setShell(ShellProviderInterface $shell): Options
    {
        $this->shell = $shell;

        return $this;
    }

    public function getShell(): ?ShellProviderInterface
    {
        return $this->shell;
    }

    /**
     * Set dry-run flag.
     *
     * @return $this
     */
    public function setDryRun(bool $flag): static
    {
        $this->dryRun = $flag;

        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function setQuiet(bool $quiet): Options
    {
        $this->quiet = $quiet;

        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl($baseUrl): Options
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function getDynamicOptions(): array
    {
        return $this->dynamicOptions;
    }

    public function getScaffoldDefinition(): ?Node
    {
        return $this->definition;
    }

    public function setScaffoldDefinition(Node $definition): Options
    {
        $this->definition = $definition;

        return $this;
    }

    public function getRootPath(): ?string
    {
        return $this->rootPath;
    }

    public function setRootPath($root_path): Options
    {
        $this->rootPath = $root_path;

        return $this;
    }
}
