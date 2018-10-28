<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\ShellProviderInterface;

class FilesMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'files';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'files';
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $return =  parent::getDefaultConfig($configuration_service, $host_config);
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'tar' => 'tar'
        ];

        return $return;
    }

    public function putFile(HostConfig $config, TaskContextInterface $context)
    {
        $source = $context->get('sourceFile', false);
        if (!$source) {
            $context->setResult('exitCode', 1);
            return;
        }
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($config, $context);
        $shell->putFile($source, $config['rootFolder'], $context, true);
    }

    public function getFile(HostConfig $config, TaskContextInterface $context)
    {
        $source = $context->get('sourceFile', false);
        $dest = $context->get('destFile', false);
        if (!$source || !$dest) {
            $context->setResult('exitCode', 1);
            return;
        }
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($config, $context);
        $shell->getFile($source, $dest, $context, true);
    }

    public function backup(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $what = $context->get('what', []);
        if (!in_array('files', $what)) {
            return;
        }

        $basename = $context->getResult('basename');
        $backup_file_name = $host_config['backupFolder'] . '/' . implode('--', $basename) . '.tgz';

        $backup_file_name = $this->backupFiles($host_config, $context, $shell, $backup_file_name);

        $this->logger->notice('Files dumped to `' . $backup_file_name . '`');
    }

    private function backupFiles(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $backup_file_name
    ) {
        $source_folders = $context->get('sourceFolders', []);
        $keys = ['filesFolder', 'privateFilesFolder'];
        foreach ($keys as $key) {
            if (!empty($host_config[$key])) {
                $source_folders[]= $host_config[$key];
            }
        }

        $this->tarFiles($host_config, $context, $shell, $source_folders, $backup_file_name, 'backup');
        return $backup_file_name;

    }

    private function tarFiles(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $source_folders,
        string $backup_file_name,
        string $type
    ) {
        $exclude_files = $context->getConfigurationService()->getSetting('excludeFiles.' . $type, false);
        $cmd = '#!tar';

        if ($exclude_files) {
            $cmd .= ' --exclude="' . implode('" --exclude="', $exclude_files) . '"';
        }
        $cmd .= ' -czPf ' . $backup_file_name;
        $cmd .= ' ' . implode(' ', $source_folders);
        $result = $shell->run($cmd);

    }
}