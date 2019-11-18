<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\SetAndRestoreObjProperty;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class LocalShellProvider extends BaseShellProvider implements ShellProviderInterface
{

    const RESULT_IDENTIFIER = '##RESULT:';
    const PROVIDER_NAME = 'local';

    /** @var Process|null */
    private $process;

    /** @var InputStream */
    private $input;

    protected $captureOutput = false;

    public function getName(): string
    {
        return 'local';
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $result = parent::getDefaultConfig($configuration_service, $host_config);
        $result['shellExecutable'] = $configuration_service->getSetting('shellExecutable', '/bin/bash');
        $result['shellProviderExecutable'] = $configuration_service->getSetting('shellProviderExecutable', '/bin/bash');

        return $result;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $validator = new ValidationService($config, $errors, 'host-config');
        $validator->hasKey(
            'shellExecutable',
            'Missing shellExecutable, should point to the executable to run an interactive shell'
        );
    }

    public function createShellProcess(array $command = [], array $options = []): Process
    {
        $shell_command = $this->getShellCommand($options);
        if (count($command) > 0) {
            $shell_command = array_merge($shell_command, $command);
        }
        $this->logger->info('Starting shell with ' . implode(' ', $shell_command));

        $process = new Process(
            $shell_command,
            getcwd(),
            [
                'LANG' => '',
                'LC_CTYPE' => 'POSIX',
            ]
        );

        $process->setTimeout(0);

        return $process;
    }

    /**
     * Setup local shell.
     *
     * @throws \RuntimeException
     */
    public function setup()
    {
        if ($this->process) {
            return;
        }

        if (empty($this->hostConfig)) {
            throw new \RuntimeException('No host-config set for local shell provider');
        }

        $shell_executable = $this->hostConfig['shellExecutable'];
        $this->process = $this->createShellProcess([$shell_executable]);

        $this->input = new InputStream();
        $this->process->setInput($this->input);

        $this->process->start(function ($type, $buffer) {
            $lines = explode(PHP_EOL, $buffer);
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                if ($type == Process::ERR) {
                    if (!$this->captureOutput) {
                        fwrite(STDERR, $line . PHP_EOL);
                    } else {
                        $this->logger->debug(trim($line));
                    }
                } elseif ((!$this->captureOutput) && strpos($line, self::RESULT_IDENTIFIER) === false) {
                    if ($this->output) {
                        $this->output->writeln($line);
                    } else {
                        fwrite(STDOUT, $line . PHP_EOL);
                    }
                }
            }
        });
        if (!empty($this->hostConfig['environment'])) {
            $environment = $this->hostConfig['environment'];

            $variables = [
                'settings' => $this->hostConfig->getConfigurationService()->getAllSettings(),
                'host' => $this->hostConfig->raw(),
            ];
            $replacements = Utilities::expandVariables($variables);
            $environment = Utilities::expandStrings($environment, $replacements);

            $this->applyEnvironment($environment);
        }
    }

    /**
     * Run a command in the shell.
     *
     * @param string $command
     * @param bool $capture_output
     * @param bool $throw_exception_on_error
     * @return CommandResult
     * @throws FailedShellCommandException
     * @throws \RuntimeException
     */
    public function run(string $command, $capture_output = false, $throw_exception_on_error = true): CommandResult
    {
        $scoped_capture_output = new SetAndRestoreObjProperty('captureOutput', $this, $capture_output);

        $this->setup();
        $this->process->clearErrorOutput();
        $this->process->clearOutput();

        $command = sprintf("cd %s && %s", $this->getWorkingDir(), $this->expandCommand($command));
        if (substr($command, -1) == ';') {
            $command = substr($command, 0, -1);
        }
        $this->logger->log($this->loglevel->get(), $command);

        
        // Send to shell.
        $input = $command . '; echo "' . self::RESULT_IDENTIFIER . '$?"' . PHP_EOL;
        $this->input->write($input);

        // Get result.
        $result = '';
        while ((strpos($result, self::RESULT_IDENTIFIER) === false) && !$this->process->isTerminated()) {
            $partial = $this->process->getIncrementalOutput();
            $result .=  $partial;
            if (empty($partial) && !$this->process->isTerminated()) {
                usleep(1000 * 100);
            }
        }
        if ($this->process->isTerminated()) {
            $this->logger->log($this->errorLogLevel->get(), 'Local shell terminated unexpected!');
            $error_output = trim($this->process->getErrorOutput());
            $this->logger->log($this->errorLogLevel->get(), $error_output);
            $exit_code = $this->process->getExitCode();
            $this->process = null;
            $cr = new CommandResult($exit_code, explode(PHP_EOL, $error_output));
            if ($throw_exception_on_error) {
                $cr->throwException(sprintf('`%s` failed!', $command));
            }
            return $cr;
        }

        $lines = explode(PHP_EOL, $result);
        do {
            $exit_code = array_pop($lines);
        } while (empty($exit_code));

        $exit_code = intval(str_replace(self::RESULT_IDENTIFIER, '', $exit_code), 10);

        $cr = new CommandResult($exit_code, $lines);
        if ($cr->failed() && !$capture_output && $throw_exception_on_error) {
            $cr->throwException(sprintf('`%s` failed!', $command));
        }
        return $cr;
    }

    /**
     * Setup environment variables..
     *
     * @param array $environment
     * @throws \Exception
     */
    public function applyEnvironment(array $environment)
    {
        $files = [
            '/etc/profile',
            '~/.bashrc'
        ];
        foreach ($files as $file) {
            if ($this->exists($file)) {
                $this->run(sprintf('. %s', $file));
            }
        }
        foreach ($environment as $key => $value) {
            $this->run("export \"$key\"=\"$value\"");
        }
    }

    public function getShellCommand(array $options = []):array
    {
        return [];
    }

    public function exists($file): bool
    {
        return file_exists($file);
    }

    /**
     * @param string $source
     * @param string $dest
     * @param TaskContextInterface $context
     * @param bool $verbose
     * @return bool
     * @throws \Exception
     */
    public function putFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        $this->cd($context->getConfigurationService()->getFabfilePath());
        $result = $this->run(sprintf('cp -r "%s" "%s"', $source, $dest));
        $context->setResult('targetFile', $dest);

        return $result->succeeded();
    }

    /**
     * @param string $source
     * @param string $dest
     * @param TaskContextInterface $context
     * @param bool $verbose
     * @return bool
     * @throws \Exception
     */
    public function getFile(string $source, string $dest, TaskContextInterface $context, bool $verbose = false): bool
    {
        return $this->putFile($source, $dest, $context, $verbose);
    }


    public function startRemoteAccess(
        string $ip,
        int $port,
        string $public_ip,
        int $public_port,
        HostConfig $config,
        TaskContextInterface $context
    ) {
        throw new \InvalidArgumentException('Local shells cannot handle startRemoteAccess!');
    }

    public function createTunnelProcess(HostConfig $target_config, array $prefix = [])
    {
        throw new \InvalidArgumentException('Local shells cannot handle tunnels!');
    }

    /**
     * {@inheritdoc}
     */
    public function wrapCommandInLoginShell(array $command)
    {
        array_unshift(
            $command,
            '/bin/bash',
            '--login',
            '-c'
        );
        return $command;
    }
}
