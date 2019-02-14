<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\ShellProvider\ShellProviderInterface;

class FilesMethod extends BaseMethod implements MethodInterface
{

    const DEFAULT_FILE_SOURCES = [
        'public' => 'filesFolder',
        'private' => 'privateFilesFolder'
    ];

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
        $return = parent::getDefaultConfig($configuration_service, $host_config);
        $return['tmpFolder'] = '/tmp';
        $return['executables'] = [
            'tar' => 'tar',
            'rsync' => 'rsync'
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
        $keys = $context->get('backupFolderKeys', []);
        $keys = array_merge($keys, self::DEFAULT_FILE_SOURCES);

        foreach ($keys as $key => $folder) {
            if (empty($host_config[$folder])) {
                continue;
            }
            $backup_file_name = $host_config['backupFolder'] . '/' . implode('--', $basename) . '.' . $key . '.tgz';
            $source_folders = [$host_config[$folder]];

            $backup_file_name = $this->backupFiles($host_config, $context, $shell, $source_folders, $backup_file_name);

            if (!$backup_file_name) {
                $this->logger->error('Could not backup files ' . implode(' ', $source_folders));
            } else {
                $this->logger->notice('Files dumped to `' . $backup_file_name . '`');

                $context->addResult('files', [[
                    'type' => 'files',
                    'file' => $backup_file_name
                ]]);
            }
        }
    }

    private function backupFiles(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $source_folders,
        string $backup_file_name
    ) {
        return $this->tarFiles($host_config, $context, $shell, $source_folders, $backup_file_name, 'backup');
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

        return $result->succeeded() ? $backup_file_name : false;
    }

    public function listBackups(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $files = $this->getRemoteFiles($shell, $host_config['backupFolder'], ['*.tgz']);
        $result = [];
        foreach ($files as $file) {
            $tokens = $this->parseBackupFile($host_config, $file, 'files');
            if ($tokens) {
                $result[] = $tokens;
            }
        }

        $context->addResult('files', $result);
    }

    public function restore(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $what = $context->get('what', []);
        if (!in_array('files', $what)) {
            return;
        }

        $backup_set = $context->get('backup_set', []);
        foreach ($backup_set as $elem) {
            if ($elem['type'] != 'files') {
                continue;
            }
            $file_type = $this->getFileTypeFromFileName($elem['file']);
            if (empty($host_config[$file_type])) {
                $this->logger->error(
                    'Could not find configuration for file-type `' . $file_type . '`, skipping restore'
                );
                continue;
            }

            $target_dir = $host_config[$file_type];
            $result = $this->extractFiles($shell, $host_config['backupFolder'] . '/' . $elem['file'], $target_dir);
            if (!$result->succeeded()) {
                $result->throwException('Could not restore backup from ' . $elem['file']);
            }
            $context->addResult('files', [[
                'type' => 'files',
                'file' => $elem['file']
            ]]);
        }
    }

    private function extractFiles(
        ShellProviderInterface $shell,
        string $archive,
        string $target_dir
    ) {
        $this->logger->notice('Extracting ' . $archive . ' to ' . $target_dir);

        if ($shell->exists($target_dir)) {
            // Rename and move away.
            $backup = $target_dir . date('YmdHms');
            $shell->run(sprintf('chmod u+w %s', $target_dir));
            $shell->run(sprintf('mv %s %s', $target_dir, $backup));
        }
        $shell->run(sprintf('mkdir -p %s', $target_dir));
        $saved = $shell->getWorkingDir();
        $shell->cd($target_dir);
        $result = $shell->run(sprintf('#!tar -xzPf %s', $archive));
        $shell->cd($saved);

        return $result;
    }

    private function getFileTypeFromFileName($file)
    {
        $mapping = [
            'private' => 'privateFilesFolder',
            'public' => 'filesFolder',
            'tgz' => 'filesFolder',
        ];
        $p = strrpos($file, '--');
        $p2 = strpos(substr($file, $p + 2), '.');
        $temp = substr($file, $p + 2 + $p2 + 1);
        $a = explode('.', $temp);
        $type = $a[0];

        return isset($mapping[$type]) ? $mapping[$type] : $type;
    }

    public function getFilesDump(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $keys = $context->get('backupFolderKeys', []);
        $keys = array_merge($keys, self::DEFAULT_FILE_SOURCES);
        foreach ($keys as $key => $name) {
            if (!empty($host_config[$name])) {
                $filename = $host_config['tmpFolder'] .
                    '/' . $host_config['configName'] .
                    '.' . $key . '.'
                    . date('YmdHms') . '.tgz';
                $filename = $this->tarFiles($host_config, $context, $shell, [$host_config[$name]], $filename, $key);

                if ($filename) {
                    $context->addResult('files', [$filename]);
                }
            }
        }
    }

    public function copyFrom(HostConfig $host_config, TaskContextInterface $context)
    {
        $what = $context->get('what');
        if (!in_array('files', $what)) {
            return;
        }

        /** @var HostConfig $from_config */
        /** @var ShellProviderInterface $shell */
        /** @var ShellProviderInterface $from_shell */
        $from_config = $context->get('from', false);
        $shell = $this->getShell($host_config, $context);

        $keys = ['filesFolder', 'privateFilesFolder'];
        foreach ($keys as $key) {
            if (!empty($host_config[$key]) && !empty($from_config[$key])) {
                $this->rsync($host_config, $from_config, $context, $key);
            }
        }
    }


    private function rsync(HostConfig $to_config, HostConfig $from_config, TaskContextInterface $context, string $key)
    {
        $from_path = $from_config[$key];
        $to_path = $to_config[$key];

        $this->logger->notice(sprintf(
            'Syncing files from `%s` to `%s`',
            $from_config['configName'],
            $to_config['configName']
        ));
        $exclude_settings = $context->getConfigurationService()->getSetting('excludeFiles.copyFrom', false);
        $rsync_args = '';
        if ($exclude_settings) {
            $rsync_args .= ' --exclude "' . implode('" --exclude "', $exclude_settings) . '"';
        }
        $rsync_args .= ' -rav --no-o --no-g -e "' . sprintf(
            'ssh -T -o Compression=no ' .
            '-o PasswordAuthentication=no ' .
            '-o StrictHostKeyChecking=no ' .
            '-o UserKnownHostsFile=/dev/null ' .
            '-p %s',
            $from_config['port']
        ) . '"';
        $rsync_args .= sprintf(
            ' %s@%s:%s/. %s',
            $from_config['user'],
            $from_config['host'],
            $from_path,
            $to_path
        );

        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($to_config, $context);
        return $shell->run('#!rsync ' . $rsync_args);
    }
}
