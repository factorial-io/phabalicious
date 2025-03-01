<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\ShellProvider\TunnelHelper\TunnelHelperFactory;
use Phabalicious\Utilities\AppDefaultStages;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;

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

    public function getMethodDependencies(MethodFactory $factory, \ArrayAccess $data): array
    {
        return [];
    }

    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors,
    ): void {
    }

    public function getKeysForDisallowingDeepMerge(): array
    {
        return [];
    }

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        return new Node([], $this->getName().' global settings');
    }

    public function validateGlobalSettings(Node $settings, ValidationErrorBagInterface $errors): void
    {
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        return new Node([], $this->getName().' method defaults');
    }

    public function alterConfig(ConfigurationService $configuration_service, Node $data): void
    {
        // Intentionally left blank.
    }

    public function createShellProvider(array $host_config): ?ShellProviderInterface
    {
        return null;
    }

    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context): void
    {
        // $this->logger->debug('preflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
        if ($this->tunnelHelperFactory) {
            $this->tunnelHelperFactory->prepareTunnels($task, $config, $context);
        }
    }

    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context): void
    {
        // $this->logger->debug('postflightTask ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function fallback(string $task, HostConfig $config, TaskContextInterface $context): void
    {
        // $this->logger->debug('fallback ' . $task . ' on ' . $this->getName(), [$config, $context]);
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        if ('appCreate' === $task) {
            $stage = $context->get('currentStage');

            return AppDefaultStages::stageNeedsRunningApp($stage);
        }

        return false;
    }

    /**
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function executeCommand(TaskContext $context, string $command_name, array $in_args): int
    {
        $args = [];
        $command = $context->getCommand()->getApplication()->find($command_name);
        if (isset($in_args[0])) {
            $args[$command_name] = $in_args[0];
            unset($in_args[0]);
        }
        $variables = $context->get('variables', []);

        // Passing arguments and secrets to the command to execute
        if ($command->getDefinition()->hasOption('arguments')
            && !empty($variables['arguments'])
            && is_array($variables['arguments'])
        ) {
            $args['--arguments'] = Utilities::buildOptionsForArguments($variables['arguments']);
        }
        if ($command->getDefinition()->hasOption('secret') && $context->getInput()->hasOption('secret')) {
            $args['--secret'] = $context->getInput()->getOption('secret');
        }
        $args['--config'] = $context->get('host_config')->getConfigName();
        array_unshift($in_args, $command_name);
        $argv_input = new StringInput(implode(' ', $in_args));
        $argv_input->bind($command->getDefinition());

        foreach ($argv_input->getOptions() as $name => $option) {
            if (empty($option)) {
                continue;
            }
            $name = '--'.$name;
            if (isset($args[$name])) {
                if (is_array($args[$name])) {
                    $args[$name] = array_merge($args[$name], $option);
                } else {
                    // Discard this option, as we already have one.
                    continue;
                }
            } else {
                $args[$name] = $option;
            }
        }

        $input = new ArrayInput($args);

        return $command->run($input, $context->getOutput());
    }

    public function getShell(HostConfig $host_config, TaskContextInterface $context): ?ShellProviderInterface
    {
        return $context->get('shell', $host_config->shell());
    }

    public static function getRemoteFiles(ShellProviderInterface $shell, string $folder, array $patterns)
    {
        $pwd = $shell->getWorkingDir();
        $shell->cd($folder);

        $result = [];
        foreach ($patterns as $pattern) {
            $return = $shell->run('ls -l '.$pattern.' 2>/dev/null', RunOptions::CAPTURE_AND_HIDE_OUTPUT);
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

    public function getDeprecatedValuesMapping(): array
    {
        return [];
    }

    protected function parseBackupFile(HostConfig $host_config, string $file, string $type)
    {
        $p = strrpos($file, '--');
        $p2 = strpos($file, '.', $p + 2);
        $hash = substr($file, 0, $p2);
        $tokens = explode('--', $hash);
        if (count($tokens) < 3) {
            return false;
        }

        if ($tokens[0] != $host_config->getConfigName()) {
            [$tokens[1], $tokens[0]] = $tokens;
        }

        if ($tokens[0] != $host_config->getConfigName()) {
            return false;
        }

        if (3 == count($tokens)) {
            // No commit hash.
            return [
                'config' => $tokens[0],
                'date' => $tokens[1],
                'time' => $tokens[2],
                'type' => $type,
                'hash' => $hash,
                'file' => $file,
            ];
        }

        return [
            'config' => $tokens[0],
            'commit' => $tokens[1],
            'date' => $tokens[2],
            'time' => $tokens[3],
            'type' => $type,
            'hash' => $hash,
            'file' => $file,
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

    public function getDeprecationMapping(): array
    {
        return [];
    }
}
