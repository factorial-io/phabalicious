<?php

namespace Phabalicious\Utilities;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderFactory;
use Phabalicious\ShellProvider\ShellProviderInterface;

final class EnsureKnownHosts
{
    /**
     * Ensure a list of known hosts.
     *
     * @throws FailedShellCommandException
     */
    public static function ensureKnownHosts(
        ConfigurationService $config,
        array $known_hosts,
        ?ShellProviderInterface $shell = null,
    ) {
        if (!$shell) {
            $shell = ShellProviderFactory::create('local', $config->getLogger());
            $host_config = new HostConfig([
                'rootFolder' => getcwd(),
                'shellExecutable' => '/bin/bash',
            ], $shell, $config);
            $shell->setHostConfig($host_config);
        }
        foreach ($known_hosts as $host) {
            $h = false;
            if (false !== strpos($host, ':')) {
                [$h, $p] = @explode(':', $host);
                if ('22' !== $p) {
                    $host_str = sprintf('[%s]:%d', $h, $p);
                } else {
                    $host_str = $h;
                }
            } else {
                $p = 22;
                $host_str = $host;
            }
            $result = $shell->run(sprintf('ssh-keygen -F %s  2>/dev/null 1>/dev/null', $host_str), RunOptions::CAPTURE_AND_HIDE_OUTPUT);
            if ($result->failed()) {
                $config->getLogger()->info(sprintf('%s not in known_hosts, adding it now.', $host));
                $result = $shell->run(sprintf(
                    'ssh-keyscan -t rsa -T 10 -p %d %s  >> ~/.ssh/known_hosts',
                    $p,
                    $h
                ), RunOptions::CAPTURE_AND_HIDE_OUTPUT);
                if ($result->failed()) {
                    $config->getLogger()->notice(sprintf('Could not add host %s to known_hosts', $host));
                }
            }
        }
    }
}
