<?php

namespace Phabalicious\ShellProvider;

use Psr\Log\LoggerInterface;

class ShellProviderFactory
{
    public static function create(string $shell_provider_name, LoggerInterface $logger): ShellProviderInterface
    {
        switch ($shell_provider_name) {
            case LocalShellProvider::PROVIDER_NAME:
                $shell_provider = new LocalShellProvider($logger);
                break;

            case SshShellProvider::PROVIDER_NAME:
                $shell_provider = new SshShellProvider($logger);
                break;

            case DockerExecShellProvider::PROVIDER_NAME:
                $shell_provider = new DockerExecShellProvider($logger);
                break;

            case DockerExecOverSshShellProvider::PROVIDER_NAME:
                $shell_provider = new DockerExecOverSshShellProvider($logger);
                break;

            case KubectlShellProvider::PROVIDER_NAME:
                $shell_provider = new KubectlShellProvider($logger);
                break;

            case ScottyShellProvider::PROVIDER_NAME:
                $shell_provider = new ScottyShellProvider($logger);
                break;

            default:
                $shell_provider = null;
        }

        return $shell_provider;
    }
}
