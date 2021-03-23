<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\ShellProvider\TunnelHelper\TunnelHelperFactory;
use Phabalicious\Utilities\AppDefaultStages;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;

abstract class BaseMethod implements MethodInterface
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /** @var TunnelHelperFactory */
    protected $tunnelHelperFactory;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setTunnelHelperFactory(TunnelHelperFactory $tunnel_helper_factory)
    {
        $this->tunnelHelperFactory = $tunnel_helper_factory;
    }

    public function getOverriddenMethod()
    {
        return false;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
    }

    public function getKeysForDisallowingDeepMerge(): array
    {
        return [];
    }

    public function getGlobalSettings(): array
    {
        return [];
    }

    public function validateGlobalSettings(array $settings, ValidationErrorBagInterface $errors)
    {
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
        // $this->logger->debug('preflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
        if ($this->tunnelHelperFactory) {
            $this->tunnelHelperFactory->prepareTunnels($task, $config, $context);
        }
    }

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        // $this->logger->debug('postflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function fallback(string $task, HostConfig $config, TaskContextInterface $context)
    {
        // $this->logger->debug('fallback ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task)
    {
        if ($task == 'appCreate') {
            $stage = $context->get('currentStage');
            return AppDefaultStages::stageNeedsRunningApp($stage);
        }

        return false;
    }

    /**
     * @param TaskContext $context
     * @param string $command_name
     * @param array $args
     *
     * @return int
     * @throws \Exception
     */
    public function executeCommand(TaskContext $context, $command_name, $args)
    {
        /** @var Command $command */
        $command = $context->getCommand()->getApplication()->find($command_name);
        if (isset($args[0])) {
            $args[$command_name] = $args[0];
            unset($args[0]);
        }
        $variables = $context->get('variables', []);
        if ($command->getDefinition()->hasOption('arguments') &&
            !empty($variables['arguments']) &&
            is_array($variables['arguments'])
        ) {
            $args['--arguments'] = Utilities::buildOptionsForArguments($variables['arguments']);
        }
        $args['--config'] = $context->get('host_config')['configName'];
        $input = new ArrayInput($args);
        return $command->run($input, $context->getOutput());
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @return ShellProviderInterface|null
     */
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
            [$tokens[1], $tokens[0]] = $tokens;
        }

        if ($tokens[0] != $host_config['configName']) {
            return false;
        }

        if (count($tokens) == 3) {
            // No commit hash.
            return [
                'config' => $tokens[0],
                'date' => $tokens[1],
                'time' => $tokens[2],
                'type' => $type,
                'hash' => $hash,
                'file' => $file
            ];
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

    public function getKnownHosts(HostConfig $host_config, TaskContextInterface $context)
    {
        return $host_config->get('knownHosts', $context->getConfigurationService()->getSetting('knownHosts', []));
    }

    public function getRootFolderKey(): string
    {
        return 'rootFolder';
    }
}
