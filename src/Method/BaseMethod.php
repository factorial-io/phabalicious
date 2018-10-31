<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;

abstract class BaseMethod implements MethodInterface
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getOverriddenMethod()
    {
        return false;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
    }

    public function getGlobalSettings(): array
    {
        return [];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        return [];
    }

    public function alterConfig(ConfigurationService $configuration_service, array &$data)
    {
        // Intentionally left blank.
    }

    public function createShellProvider(array $host_config)
    {
    }

    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->debug('preflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->debug('postflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function fallback(string $task, HostConfig $config, TaskContextInterface $context)
    {
        $this->logger->debug('fallback ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    /**
     * @param TaskContext $context
     * @param $command_name
     * @param $args
     * @throws \Exception
     */
    public function executeCommand(TaskContext $context, $command_name, $args)
    {
        /** @var \Symfony\Component\Console\Command\Command $command */
        $command = $context->getCommand()->getApplication()->find($command_name);
        if (isset($args[0])) {
            $args[$command_name] = $args[0];
            unset($args[0]);
        }
        $args['--config'] = $context->get('host_config')['configName'];
        $input = new ArrayInput($args);
        $command->run($input, $context->getOutput());
    }

    public function getShell(HostConfig $host_config, TaskContextInterface $context)
    {
        return $context->get('shell', $host_config->shell());
    }

    protected function getRemoteFiles(ShellProviderInterface $shell, string $folder, array $patterns)
    {
        $pwd = $shell->getWorkingDir();
        $shell->cd($folder);

        $result = [];
        foreach ($patterns as $pattern) {
            $return = $shell->run('ls -l ' . $pattern . ' 2>/dev/null', true);
            foreach ($return->getOutput() as $line) {
                $a = preg_split('/\s+/', $line);
                if (count($a) >= 8) {
                    $result[] = $a[8];
                }
            }
        }
        $shell->cd($pwd);

        return $result;
    }

    protected function parseBackupFile(HostConfig $host_config, string $file, string $type)
    {
        $p = strrpos($file, '--');
        $p2 = strpos($file, '.', $p+2);
        $hash = substr($file, 0, $p2);
        $tokens = explode('--', $hash);
        if (count($tokens) < 3) {
            return false;
        }

        if ($tokens[0] != $host_config['configName']) {
            list($tokens[1], $tokens[0]) = $tokens;
        }

        if ($tokens[0] != $host_config['configName']) {
            return false;
        }

        return [
            'config' => $tokens[0],
            'commit' => $tokens[1],
            'date' => $tokens[2],
            'time' => $tokens[3],
            'type' => $type,
            'hash' => $hash,
            'file' => $file
        ];
    }

}