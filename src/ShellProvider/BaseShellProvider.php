<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class BaseShellProvider implements ShellProviderInterface
{

    /** @var HostConfig */
    protected $hostConfig;

    /** @var string */
    private $workingDir;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var OutputInterface */
    protected $output;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [
            'rootFolder' => $configuration_service->getFabfilePath(),
        ];
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        $validator = new ValidationService($config, $errors, 'host-config');
        $validator->hasKey('rootFolder', 'Missing rootFolder, should point to the root of your application');
    }

    public function setHostConfig(HostConfig $config)
    {
        $this->hostConfig = $config;
        $this->workingDir = $config['rootFolder'];
    }

    public function getHostConfig(): HostConfig
    {
        return $this->getHostConfig();
    }

    public function getWorkingDir(): string
    {
        return $this->workingDir;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function cd(string $dir): ShellProviderInterface
    {
        $this->workingDir = $dir;
        $this->logger->debug('New working dir: ' . $dir);

        return $this;
    }

    /**
     * Expand a command.
     *
     * @param $line
     * @return null|string|string[]
     */
    protected function expandCommand($line)
    {
        if (empty($this->hostConfig['executables'])) {
            return $line;
        }
        $pattern = implode('|', array_map(function ($elem) {
            return preg_quote('#!' . $elem) . '|' . preg_quote('$$' . $elem);
        }, array_keys($this->hostConfig['executables'])));

        $cmd = preg_replace_callback('/' . $pattern . '/', function ($elem) {
            return $this->hostConfig['executables'][substr($elem[0], 2)];
        }, $line);

        return $cmd;
    }

    public function runCommand(array $cmd, TaskContextInterface $context, $interactive = false):bool
    {
        $stdin = $interactive ? fopen('php://stdin', 'r') : null;
        $this->logger->notice('running command: ' . implode(' ', $cmd));
        $process = new Process($cmd, $context->getConfigurationService()->getFabfilePath(), [], $stdin);
        if ($interactive) {
            $process->setTimeout(0);
            $process->setTty(true);
            $process->start();
            $process->wait(function ($type, $buffer) {
                if ($type == Process::ERR) {
                    fwrite(STDERR, $buffer);
                } else {
                    fwrite(STDOUT, $buffer);
                }
            });
        } else {
            $process->run();
        }
        if ($process->getExitCode() != 0) {
            $this->logger->error($process->getErrorOutput());
            return false;
        }
        return true;
    }

}