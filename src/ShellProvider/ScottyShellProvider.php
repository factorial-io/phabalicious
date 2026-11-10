<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\SetAndRestoreObjProperty;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Process\Process;

/**
 * Executes commands inside a scotty app service via `scottyctl app:shell`.
 *
 * In contrast to the other shell providers, this one does not keep a
 * long-running shell open and pipe commands into its stdin: scottyctl does not
 * forward stdin in `--command` mode and needs a terminal for its interactive
 * mode, so a persistent shell hangs when running without a tty (e.g. in CI).
 * Instead every command is executed with its own `scottyctl app:shell
 * --command` call, which propagates the exit code of the remote command.
 */
class ScottyShellProvider extends LocalShellProvider
{
    public const PROVIDER_NAME = 'scotty';

    /**
     * Commands to prepend to every command, e.g. to export the environment.
     *
     * @var string[]
     */
    protected array $commandPrefix = [];

    protected bool $initialized = false;

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $result = [
            'shellExecutable' => '/bin/sh',
        ];

        return $parent->merge(new Node($result, $this->getName().' shellprovider defaults'));
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors): void
    {
        parent::validateConfig($config, $errors);

        $validation = new ValidationService($config, $errors, 'host-config');
        $validation->hasKeys([
            'scotty' => 'The scotty configuration to use',
        ]);

        if (!$errors->hasErrors()) {
            $scotty_validation = new ValidationService(
                $config['scotty'],
                $errors,
                'host:scotty'
            );
            $scotty_validation->hasKey(
                'shellService',
                'The service name to connect to for shell access'
            );
        }
    }

    public function setHostConfig(HostConfig $config): void
    {
        parent::setHostConfig($config);
        $this->initialized = false;
        $this->commandPrefix = [];

        // The host's rootFolder points to the local checkout (it defaults to
        // the folder of the fabfile), which does not exist in the container.
        // Use scotty.rootFolder if set, otherwise use the working dir of the
        // container.
        $this->setWorkingDir($config['scotty']['rootFolder'] ?? '');
    }

    public function setup(): void
    {
        if ($this->initialized) {
            return;
        }
        if (empty($this->hostConfig)) {
            throw new \RuntimeException('No host-config set for scotty shell provider');
        }

        $environment = [];
        if (!empty($this->hostConfig['environment'])) {
            $environment = $this->hostConfig['environment'];

            $variables = [
                'settings' => $this->hostConfig->getConfigurationService()->getAllSettings(),
                'host' => $this->hostConfig->asArray(),
            ];
            $replacements = Utilities::expandVariables($variables);
            $environment = Utilities::expandStrings($environment, $replacements);
            $password_manager = $this->hostConfig->getConfigurationService()->getPasswordManager();
            if ($password_manager) {
                $environment = $password_manager->resolveSecrets($environment);
            }
        }
        $this->commandPrefix = $this->getApplyEnvironmentCmds($environment);
        $this->initialized = true;
    }

    public function terminate(): void
    {
        parent::terminate();
        $this->initialized = false;
    }

    /**
     * Run a single command in the container via `scottyctl app:shell --command`.
     */
    public function run(string $command, RunOptions $run_options = RunOptions::NONE, $throw_exception_on_error = true): CommandResult
    {
        $scoped_run_options = new SetAndRestoreObjProperty('runOptions', $this, $run_options);
        $this->setup();

        $password_manager = $this->getHostConfig()
            ->getConfigurationService()
            ->getPasswordManager();
        if ($password_manager) {
            $command = $password_manager->resolveSecrets($command);
        }

        $command = $this->expandCommand($command);
        if (str_ends_with($command, ';')) {
            $command = substr($command, 0, -1);
        }
        if ('' !== $this->getWorkingDir()) {
            $command = sprintf('cd %s && %s', $this->getWorkingDir(), $command);
        }
        $this->logger->log($this->loglevel->get(), $command);

        $remote_command = implode(' && ', array_merge($this->commandPrefix, [$command]));
        $process = $this->createShellProcess([$remote_command], new ShellOptions());
        $process->run(function ($type, $buffer) use ($run_options) {
            if (Process::ERR === $type) {
                // scottyctl reports its status on stderr, e.g. "WebSocket
                // authenticated", do not mix it into the command output.
                $this->logger->debug(trim($buffer));

                return;
            }
            if (!$run_options->hideOutput()) {
                $buffer = str_replace("\r\n", "\n", $buffer);
                if ($this->output) {
                    $this->output->write($buffer);
                } else {
                    fwrite(STDOUT, $buffer);
                }
            }
        });

        $exit_code = (int) $process->getExitCode();
        $lines = $this->splitOutput($process->getOutput());
        if ($exit_code && empty($lines)) {
            $lines = $this->splitOutput($process->getErrorOutput());
        }

        $cr = new CommandResult($exit_code, $lines);
        if ($throw_exception_on_error && $cr->failed() && !$run_options->isCapturingOutput()) {
            $cr->throwException(sprintf('`%s` failed!', $command));
        }

        return $cr;
    }

    /**
     * Split the output into lines, the remote side runs in a tty, so lines
     * might end with CRLF.
     *
     * @return string[]
     */
    private function splitOutput(string $output): array
    {
        $lines = preg_split('/\r?\n/', rtrim($output, "\r\n"));
        $lines = array_map(fn ($line) => rtrim($line, "\r"), $lines);

        return '' === implode('', $lines) ? [] : $lines;
    }

    public function startSubShell(array $cmd): ShellProviderInterface
    {
        throw new \RuntimeException('Sub-shells are not supported by the scotty shell provider.');
    }

    public function getShellCommand(array $program_to_call, ShellOptions $options): array
    {
        $args = [
            $this->hostConfig['scotty']['app-name'],
            $this->hostConfig['scotty']['shellService'],
        ];

        // For interactive shells with TTY
        if ($options->useTty() && !$options->isShellExecutableProvided()) {
            $args[] = '--shell';
            $args[] = $this->hostConfig['shellExecutable'];
        }

        // Add any program to call
        if (count($program_to_call)) {
            $args[] = '--command';
            $args[] = implode(' ', $program_to_call);
        }

        // Use ScottyCtlOptions to build the command
        $scottyctl_args = \Phabalicious\Method\ScottyCtlOptions::buildCommand(
            $this->hostConfig->asArray(),
            'app:shell',
            $args,
            null // No context available in shell provider
        );

        $command = array_merge(
            [$this->hostConfig['executables']['scottyctl'] ?? 'scottyctl'],
            $scottyctl_args
        );

        // Resolve secrets in command array (e.g., %secret.scotty-token%)
        // This must be done here because array commands don't go through expandCommand()
        $password_manager = $this->hostConfig->getConfigurationService()->getPasswordManager();
        if ($password_manager) {
            $command = $password_manager->resolveSecrets($command);
        }

        return $command;
    }

    public function wrapCommandInLoginShell(array $command): array
    {
        return [
            '/bin/bash',
            '--login',
            '-c',
            '\''.implode(' ', $command).'\'',
        ];
    }

    public function exists($file): bool
    {
        return $this->run(sprintf('stat %s > /dev/null 2>&1', $file), RunOptions::HIDE_OUTPUT, false)
            ->succeeded();
    }

    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException('File operations are not yet supported by scottyctl. Use local shell provider for file operations.');
    }

    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        throw new \RuntimeException('File operations are not yet supported by scottyctl. Use local shell provider for file operations.');
    }
}
