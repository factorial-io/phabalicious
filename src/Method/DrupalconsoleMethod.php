<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class DrupalconsoleMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'drupal';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'drupalconsole' || $method_name === 'drupal';
    }

    public function getGlobalSettings(): array
    {
        return [
            'executables' => [
                'drupal' => 'drupal',
            ],
        ];
    }

    public function drupalConsole(HostConfig $host_config, TaskCOntextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $command = $context->get('command', false);
        if (!$command) {
            throw new \InvalidArgumentException('Missing command to run');
        }
        $this->runDrupalConsole($host_config, $shell, $command);
    }

    private function runDrupalConsole(HostConfig $host_config, ShellProviderInterface $shell, string $command)
    {
        $current = $shell->getWorkingDir();
        $shell->cd($host_config['siteFolder']);

        $root_folder = !empty($host_config['composerRootFolder'])
            ? $host_config['composerRootFolder']
            : !empty($host_config['gitRootFolder'])
                ? $host_config['gitRootFolder']
                : $host_config['rootFolder'];

        if ($shell->exists($root_folder . '/vendor/bin/drupal')) {
            $result = $shell->run(sprintf('%s/vendor/bin/drupal %s', $root_folder, $command));
        } else {
            $result = $shell->run('#!drupal ' . $command);
        }

        $shell->cd($current);

        return $result;
    }
}