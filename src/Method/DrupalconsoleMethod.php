<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\ShellProviderInterface;

class DrupalconsoleMethod extends BaseMethod implements MethodInterface
{
    public function getName(): string
    {
        return 'drupal';
    }

    public function supports(string $method_name): bool
    {
        return 'drupalconsole' === $method_name || 'drupal' === $method_name;
    }

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        return new Node([
            'executables' => [
                'drupal' => 'drupal',
            ],
        ], $this->getName().' global settings');
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        return parent::isRunningAppRequired($host_config, $context, $task) || 'drupalConsole' === $task;
    }

    private function getDrupalExec(string $root_folder, ShellProviderInterface $shell)
    {
        if ($shell->exists($root_folder.'/vendor/bin/drupal')) {
            return sprintf('%s/vendor/bin/drupal', $root_folder);
        } else {
            return '#!drupal';
        }
    }

    private function getRootFolder(HostConfig $host_config)
    {
        $keys = [
            'composer.rootFolder',
            'gitRootFolder',
            'rootFolder',
        ];
        foreach ($keys as $key) {
            if (!empty($host_config->getProperty($key))) {
                return $host_config->getProperty($key);
            }
        }
    }

    public function drupalConsole(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $shell->cd($host_config['siteFolder']);
        $command = $context->get('command', false);
        if (!$command) {
            throw new \InvalidArgumentException('Missing command to run');
        }
        $context->setResult('shell', $shell);
        $root_folder = $this->getRootFolder($host_config);
        $command = sprintf(
            'cd %s;  %s %s',
            $host_config['siteFolder'],
            $this->getDrupalExec($root_folder, $shell),
            $command
        );
        $command = $shell->expandCommand($command);
        $context->setResult('command', [
            $command,
        ]);
    }
}
