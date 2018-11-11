<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ScopedLogLevel\LogLevelStack;
use Phabalicious\ScopedLogLevel\LoglevelStackInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
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

    /** @var LogLevelStack */
    protected $loglevel;

    /** @var LogLevelStack */
    protected $errorLogLevel;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->loglevel = new LogLevelStack(LogLevel::NOTICE);
        $this->errorLogLevel = new LogLevelStack(LogLevel::ERROR);
    }

    public function getLogLevelStack(): LoglevelStackInterface
    {
        return $this->loglevel;
    }

    public function getErrorLogLevelStack(): LoglevelStackInterface
    {
        return $this->errorLogLevel;
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
        return $this->hostConfig;
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
        $line = trim($line);
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

    public static function outputCallback($type, $buffer)
    {
        if ($type == Process::ERR) {
            fwrite(STDERR, $buffer);
        } else {
            fwrite(STDOUT, $buffer);

        }
    }

    public function runCommand(array $cmd, TaskContextInterface $context, $interactive = false, $verbose = false):bool
    {
        $cb = ($verbose | $interactive)
            ? [BaseShellProvider::Class, 'outputCallback']
            : null;
        $stdin = $interactive ? fopen('php://stdin', 'r') : null;
        $this->logger->log($this->loglevel->get(), 'running command: ' . implode(' ', $cmd));
        $process = new Process($cmd, $context->getConfigurationService()->getFabfilePath(), [], $stdin);
        if ($interactive) {
            $process->setTimeout(0);
            $process->setTty(true);
            $process->start();
            $process->wait($cb);
        } else {
            $process->setTimeout(10*60);
            //$process->setTty($verbose);
            $process->run($cb);
        }
        if ($process->getExitCode() != 0) {
            $this->logger->log($this->errorLogLevel->get(), $process->getErrorOutput());
            return false;
        }
        return true;
    }

    public function copyFileFrom(
        ShellProviderInterface $from_shell,
        string $source_file_name,
        string $target_file_name,
        TaskContextInterface $context,
        bool $verbose = false
    ): bool {
        $this->logger->notice(sprintf(
            'Copy from `%s` (%s) to `%s` (%s)',
            $source_file_name,
            get_class($from_shell),
            $target_file_name,
            get_class($this)
        ));

        // This is a naive implementation, copying the file from source to local and
        // then from local to target.

        $immediate_file_name = $context->getConfigurationService()->getFabfilePath() .
            '/' . basename($source_file_name);

        $result = $from_shell->getFile($source_file_name, $immediate_file_name, $context, $verbose);
        if (!$result) {
            return false;
        }

        return $this->putFile($immediate_file_name, $target_file_name, $context, $verbose);
    }

}
