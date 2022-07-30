<?php

namespace Phabalicious\ShellProvider;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ScopedLogLevel\LogLevelStack;
use Phabalicious\ScopedLogLevel\LoglevelStackInterface;
use Phabalicious\Utilities\LogWithPrefix;
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
    private $workingDir = '';

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /** @var OutputInterface|null */
    protected $output;

    /** @var LogLevelStack */
    protected $loglevel;

    /** @var LogLevelStack */
    protected $errorLogLevel;

    /** @var string */
    protected $hash;
    /**
     * @var array
     */
    private $workingDirStack = [];

    /**
     * @var \Phabalicious\ShellProvider\FileOperationsInterface
     */
    protected $fileOperationsHandler;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = new LogWithPrefix($logger, bin2hex(random_bytes(3)));
        $this->loglevel = new LogLevelStack(LogLevel::NOTICE);
        $this->errorLogLevel = new LogLevelStack(LogLevel::ERROR);

        $this->setFileOperationsHandler(new DeferredFileOperations($this));
    }

    protected function setFileOperationsHandler(FileOperationsInterface $handler)
    {
        $this->fileOperationsHandler = $handler;
    }

    public function getLogLevelStack(): LoglevelStackInterface
    {
        return $this->loglevel;
    }

    public function getErrorLogLevelStack(): LoglevelStackInterface
    {
        return $this->errorLogLevel;
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        return new Node([
            'rootFolder' => $configuration_service->getFabfilePath(),
        ], $this->getName() . ' shellprovider defaults');
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors)
    {
        $validator = new ValidationService($config, $errors, 'host-config');
        $validator->hasKey('rootFolder', 'Missing rootFolder, should point to the root of your application');
        $validator->checkForValidFolderName('rootFolder');
    }

    public function setHostConfig(HostConfig $config)
    {
        $this->hostConfig = $config;
        $this->workingDir = $config['rootFolder'];
    }

    public function getHostConfig(): ?HostConfig
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
        if (empty($dir) || $dir[0] == '.') {
            $result = $this->run(sprintf('cd %s; echo $PWD', $dir), true, true);
            $dir = $result->getOutput()[0];
        }
        $this->workingDir = $dir;
        $this->logger->debug('New working dir: ' . $dir);

        return $this;
    }

    public function pushWorkingDir(string $new_working_dir)
    {
        $this->workingDirStack[] = $this->getWorkingDir();
        $this->cd($new_working_dir);
    }

    public function popWorkingDir()
    {
        if (count($this->workingDirStack) == 0) {
            throw new \RuntimeException('Can\'t pop working dir, stack is empty');
        }
        $working_dir = array_pop($this->workingDirStack);
        $this->cd($working_dir);
    }

    /**
     * Expand a command.
     *
     * @param string $line
     * @return null|string|string[]
     */
    public function expandCommand($line)
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

    public function runProcess(array $cmd, TaskContextInterface $context, $interactive = false, $verbose = false):bool
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
            $process->setTimeout(24*60*60);
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

        $from_shell->setOutput($this->output);

        // This is a naive implementation, copying the file from source to local and
        // then from local to target.

        $immediate_file_name = $context->getConfigurationService()->getFabfilePath() .
            '/' . basename($source_file_name);

        $result = $from_shell->getFile($source_file_name, $immediate_file_name, $context, $verbose);

        if ($result) {
            $result = $this->putFile($immediate_file_name, $target_file_name, $context, $verbose);
        }

        if (file_exists($immediate_file_name)) {
            unlink($immediate_file_name);
        }

        return $result;
    }

    /**
     * Setup environment variables..
     *
     * @param array $environment
     * @throws \Exception
     */
    public function setupEnvironment(array $environment)
    {
        $files = [
            '/etc/profile',
            '~/.bashrc'
        ];
        foreach ($files as $file) {
            if ($this->exists($file)) {
                $this->run(sprintf('. %s', $file), false, false);
            }
        }
        $this->applyEnvironment($environment);
    }

    public function getApplyEnvironmentCmds(array $environment)
    {

        $cmds = [];
        foreach ($environment as $key => $value) {
            $cmds[] = "export \"$key\"=\"$value\"";
        }
        return $cmds;
    }

    public function applyEnvironment(array $environment)
    {
        $cmds = $this->getApplyEnvironmentCmds($environment);
        if (!empty($cmds)) {
            $this->run(implode(" && ", $cmds));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRsyncOptions(
        HostConfig $to_host_config,
        HostConfig $from_host_config,
        string $to_path,
        string $from_path
    ) {
        return false;
    }

    public function getFileContents($filename, TaskContextInterface $context)
    {
        return $this->fileOperationsHandler->getFileContents($filename, $context);
    }

    public function putFileContents($filename, $data, TaskContextInterface $context)
    {
        return $this->fileOperationsHandler->putFileContents($filename, $data, $context);
    }

    public function realPath($filename, TaskContextInterface $context)
    {
        return $this->fileOperationsHandler->realPath($filename, $context);
    }
}
