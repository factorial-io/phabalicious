<?php

namespace Phabalicious\Method;

use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;

class ScriptDataBag
{
    protected $commands = [];
    protected $cleanupCommands = [];
    protected $context;
    protected $variables = [];
    protected $callbacks = [];
    protected $environment = [];
    protected $rootFolder;


    /**
     * @param array $commands
     *
     * @return ScriptDataBag
     */
    public function setCommands(array $commands): ScriptDataBag
    {
        $this->commands = $commands;
        return $this;
    }

    /**
     * @param array $cleanupCommands
     *
     * @return ScriptDataBag
     */
    public function setCleanupCommands(array $cleanupCommands): ScriptDataBag
    {
        $this->cleanupCommands = $cleanupCommands;
        return $this;
    }

    /**
     * @param mixed $context
     *
     * @return ScriptDataBag
     */
    public function setContext(TaskContextInterface $context): ScriptDataBag
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @param mixed $environment
     *
     * @return ScriptDataBag
     */
    public function setEnvironment(array $environment): ScriptDataBag
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @param mixed $rootFolder
     *
     * @return ScriptDataBag
     */
    public function setRootFolder(string $rootFolder): ScriptDataBag
    {
        $this->rootFolder = $rootFolder;
        return $this;
    }

    /**
     * @return array
     */
    public function getReplacements(): array
    {
        return Utilities::expandVariables($this->variables);
    }

    /**
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * @return array
     */
    public function getCleanupCommands(): array
    {
        return $this->cleanupCommands;
    }

    /**
     * @return mixed
     */
    public function getContext(): TaskContextInterface
    {
        return $this->context;
    }

    /**
     * @return mixed
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    public function getEnvironment(): array
    {
        $environment = $this->environment;
        return $this->expandStrings($environment);
    }

    /**
     * Expand replacements in array.
     *
     * @param array $data
     *   array with strings.
     *
     * @return array
     *   the expanded array.
     *
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    protected function expandStrings(array $data): array
    {
        $replacements = $this->getReplacements();
        $data = Utilities::expandStrings($data, $replacements);
        $data = $this
            ->getContext()
            ->getConfigurationService()
            ->getPasswordManager()
            ->resolveSecrets($data);

        return Utilities::validateScriptCommands($data, $replacements);
    }

    /**
     * Get script context data.
     */
    public function getScriptContextData(): array
    {
        $data = $this->context->get(ScriptMethod::SCRIPT_CONTEXT_DATA, []);
        return $this->expandStrings($data);
    }
    /**
     * @return mixed
     */
    public function getRootFolder(): string
    {
        return $this->rootFolder;
    }

    /**
     * @return mixed
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @param mixed $variables
     *
     * @return ScriptDataBag
     */
    public function setVariables(array $variables): ScriptDataBag
    {
        $this->variables = $variables;
        return $this;
    }

    /**
     * @return array
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    /**
     * @param array $callbacks
     *
     * @return ScriptDataBag
     */
    public function setCallbacks(array $callbacks): ScriptDataBag
    {
        $this->callbacks = $callbacks;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getShell(): ShellProviderInterface
    {
        return $this->getContext()->getShell();
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function applyReplacements(string $command): string
    {
        $command = trim($command);
        if (empty($command)) {
            return $command;
        }

        $replacements = $this->getReplacements();
        $command = Utilities::expandString($command, $replacements, []);

        return Utilities::expandAndValidateString($command, $replacements);
    }
}
