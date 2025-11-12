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

    public function setCommands(array $commands): ScriptDataBag
    {
        $this->commands = $commands;

        return $this;
    }

    public function setCleanupCommands(array $cleanupCommands): ScriptDataBag
    {
        $this->cleanupCommands = $cleanupCommands;

        return $this;
    }

    public function setContext(TaskContextInterface $context): ScriptDataBag
    {
        $this->context = $context;

        return $this;
    }

    public function setEnvironment(array $environment): ScriptDataBag
    {
        $this->environment = $environment;

        return $this;
    }

    public function setRootFolder(string $rootFolder): ScriptDataBag
    {
        $this->rootFolder = $rootFolder;

        return $this;
    }

    public function getReplacements(): array
    {
        return Utilities::expandVariables($this->variables);
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getCleanupCommands(): array
    {
        return $this->cleanupCommands;
    }

    public function getContext(): TaskContextInterface
    {
        return $this->context;
    }

    /**
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
     *                    array with strings
     *
     * @return array
     *               the expanded array
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

    public function getRootFolder(): string
    {
        return $this->rootFolder;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariables(array $variables): ScriptDataBag
    {
        $this->variables = $variables;

        return $this;
    }

    public function getCallbacks(): array
    {
        return $this->callbacks;
    }

    public function setCallbacks(array $callbacks): ScriptDataBag
    {
        $this->callbacks = $callbacks;

        return $this;
    }

    public function getShell(): ShellProviderInterface
    {
        return $this->getContext()->getShell();
    }

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
