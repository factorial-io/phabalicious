<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;

class ScottyCtlOptions
{
    protected array $data;

    public function __construct(
        protected readonly HostConfig $hostConfig,
        protected readonly TaskContextInterface $context,
    ) {
        $this->data = [];
        $scotty_data = $hostConfig->getData()->get('scotty');
        if (!$scotty_data) {
            throw new \InvalidArgumentException('Missing scotty configuration');
        }

        foreach (['server', 'access-token'] as $key) {
            if ($scotty_data->has($key)) {
                $this->data[$key] = $scotty_data->get($key)->getValue();
            }
        }
        if ($this->data['access-token']) {
            $this->context
                ->getPasswordManager()
                ->registerCustomSecretToObfuscate($this->data['access-token']);
        }

        $this->data['app-name'] =
            $scotty_data['app-name'] ?? '%host.configName%';
    }

    /**
     * Build scottyctl command arguments for any subcommand.
     *
     * @param array                     $config     Host configuration array
     * @param string                    $subcommand The scottyctl subcommand (e.g., 'app:shell', 'app:create')
     * @param array                     $args       Additional arguments for the subcommand
     * @param TaskContextInterface|null $context    Optional context for variable expansion and secret resolution
     *
     * @return array Command arguments array
     */
    public static function buildCommand(
        array $config,
        string $subcommand,
        array $args = [],
        ?TaskContextInterface $context = null,
    ): array {
        $scotty = $config['scotty'] ?? [];
        $server = $scotty['server'] ?? throw new \InvalidArgumentException('Missing scotty.server configuration');
        $access_token = $scotty['access-token'] ?? null;

        // Resolve secrets if context available
        if ($context && $access_token) {
            $access_token = $context->getPasswordManager()->resolveSecrets($access_token);
        }

        $command = ['--server', $server];

        if ($access_token) {
            $command[] = '--access-token';
            $command[] = $access_token;
        }

        $command[] = $subcommand;

        // Add additional arguments
        foreach ($args as $value) {
            $command[] = $value;
        }

        return $command;
    }

    public function getAppName(): string
    {
        return $this->data['app-name'];
    }

    public function getRestEndpoint(): string
    {
        return $this->data['server'];
    }

    public function build(string $command, array $additional_data = []): array
    {
        $variables = Utilities::buildVariablesFrom(
            $this->hostConfig,
            $this->context
        );
        $replacements = Utilities::expandVariables($variables);

        $data = Utilities::expandStrings(
            array_merge($this->data, $additional_data),
            $replacements
        );

        // Resolve secrets (e.g., %secret.scotty-token%)
        $data = $this->context->getPasswordManager()->resolveSecrets($data);

        return $this->buildImpl($data, $command);
    }

    protected function buildImpl(array $data, string $command): array
    {
        $args = [$data['app-name']];

        return self::buildCommand(
            ['scotty' => $data],
            $command,
            $args,
            null // Secrets already resolved
        );
    }

    public function runInShell(
        ShellProviderInterface $shell,
        string $command,
        array $add_data = [],
        RunOptions $run_options = RunOptions::CAPTURE_OUTPUT,
    ): CommandResult {
        return $shell->run(
            sprintf(
                '#!scottyctl %s',
                implode(' ', $this->build($command, $add_data))
            ),
            $run_options,
            false
        );
    }
}
