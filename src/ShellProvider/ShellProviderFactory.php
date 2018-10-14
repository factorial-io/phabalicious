<?php

namespace Phabalicious\ShellProvider;

class ShellProviderFactory
{

    public static function create($shell_provider_name, $logger)
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

            default:
                $shell_provider = false;
        }

        return $shell_provider;
    }
}