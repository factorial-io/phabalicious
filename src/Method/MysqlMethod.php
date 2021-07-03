<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;

class MysqlMethod extends DatabaseMethod implements MethodInterface
{
    const METHOD_NAME = 'mysql';

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::METHOD_NAME;
    }

    /**
     * @param string $method_name
     *
     * @return bool
     */
    public function supports(string $method_name): bool
    {
        return $method_name === self::METHOD_NAME;
    }

    /**
     * @return \string[][]
     */
    public function getGlobalSettings(): array
    {
        return [
            'executables' => [
                'mysql' => 'mysql',
                'grep' => 'grep',
                'mysqladmin' => 'mysqladmin',
                'mysqldump' => 'mysqladmin',
                'gunzip' => 'gunzip',
                'gzip' => 'gzip',
            ],
        ];
    }


    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     *
     * @throws \Exception
     */
    public function install(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);

        // Determine what kind of install operation this will be.

        $o = $host_config['database'] ?? false;
        if ($o && !$host_config['database']['skipCreateDatabase']) {
            $this->logger->info('Creating database ...');

            $cmd = sprintf(
                "CREATE DATABASE IF NOT EXISTS %s; GRANT ALL PRIVILEGES ON %s.* TO '%s'@'%%'; FLUSH PRIVILEGES;",
                $o['name'],
                $o['name'],
                $o['user']
            );
            try {
                $shell->run(sprintf(
                    '#!mysql -h %s -u %s --password="%s" -e "%s"',
                    $o['host'],
                    $o['user'],
                    $o['pass'],
                    $cmd
                ), false, true);
            } catch (\Exception $e) {
                $context->io()
                    ->error("Could not create database, or grant privileges!");
                $context->io()
                    ->comment("Create the db by yourself and set host.database.skipCreateDatabase to true");
                throw ($e);
            }
        }
    }


    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
     * @param array $data
     */
    public function dropDatabase(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data
    ) {
        $this->logger->notice('Dropping all tables from database ...');

        $cmd = [
            "#!mysqldump",
            "-u",
            $data['user'],
            sprintf("-p%s", $data['pass']),
            "-h",
            $data["host"],
            "--add-drop-table",
            "--no-data",
            $data['name'],
            "|",
            "#!grep ^DROP",
            "|",
            "#!mysql",
            "-u",
            $data['user'],
            sprintf("-p%s", $data['pass']),
            "-h",
            $data["host"],
            $data["name"]
        ];

        $shell->run(implode(" ", $cmd), false, true);
    }

    /**
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function exportSqlToFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $backup_file_name
    ): string {
        $data = $host_config['database'] ?: [];
        $data = $this->requestCredentialsAndWorkingDir($host_config, $context, $data);

        $context->io()->comment(sprintf('Dumping database of `%s` ...', $host_config->getConfigName()));
        $shell->pushWorkingDir($data['workingDir']);

        $cmd = [
            "#!mysqldump",
            $data["name"],
            "-u",
            $data['user'],
            sprintf("-p%s", $data['pass']),
            "-h",
            $data["host"],
            "--port",
            $data["port"] ?? "3306"
        ];

        foreach ($context->getConfigurationService()->getSetting('sqlSkipTables', []) as $table_name) {
            $cmd[] = sprintf("--ignore-table %s.%s", $data['name'], $table_name);
        }

        if (!$shell->exists(dirname($backup_file_name))) {
            $shell->run(sprintf('mkdir -p %s', dirname($backup_file_name)));
        }

        $zipped_backup = $host_config['supportsZippedBackups'];

        if ($zipped_backup) {
            $backup_file_name .= ".gz";
            $cmd[] = "| #!gzip";
        }
        $shell->run(sprintf('rm -f %s', escapeshellarg($backup_file_name)));

        $cmd[] = ">";
        $cmd[] = $backup_file_name;

        $shell->run(implode(" ", $cmd), false, true);
        $shell->popWorkingDir();

        return $backup_file_name;
    }

    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param string $file
     * @param bool $drop_db
     *
     * @return CommandResult
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function importSqlFromFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file,
        $drop_db = false
    ): CommandResult {
        $data = $host_config['database'] ?? [];
        $data = $this->requestCredentialsAndWorkingDir($host_config, $context, $data);

        $shell->pushWorkingDir($data['workingDir']);

        if ($drop_db) {
            $this->dropDatabase($host_config, $context, $shell, $data);
        }

        $this->logger->notice(sprintf('Restoring db from %s ...', $file));

        $cmd = [
            "#!mysql",
            $data["name"],
            "-u",
            $data['user'],
            sprintf("-p%s", $data['pass']),
            "-h",
            $data["host"],
            "--port",
            $data["port"] ?? "3306"
        ];

        if (substr($file, strrpos($file, '.') + 1) == 'gz') {
            array_unshift($cmd, "#!gunzip", "-c", $file, "|");
        }
        $result = $shell->run(implode(" ", $cmd));
        $shell->popWorkingDir();

        return $result;
    }

    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
     *
     * @return \Phabalicious\ShellProvider\CommandResult
     */
    public function checkDatabaseConnection(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell
    ): CommandResult {
        return $shell->run(sprintf(
            '#!mysqladmin --no-defaults -u%s --password="%s" -h %s ping',
            $host_config['database']['user'],
            $host_config['database']['pass'],
            $host_config['database']['host']
        ), true, false);
    }
}
