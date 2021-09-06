<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

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

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $config = parent::getDefaultConfig($configuration_service, $host_config);

        if (isset($host_config['database'])) {
            $config['database']['host'] = 'localhost';
        }
        foreach ([
            'mysqlDump' => [
                '--column-statistics=0',
                '--no-tablespaces'
            ],
            'mysql' => []
        ] as $key => $defaults) {
            $config[$key . 'Options'] = $configuration_service->getSetting($key . 'Options', $defaults);
        }

        return $config;
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
                "CREATE DATABASE IF NOT EXISTS \`%s\`; " .
                "GRANT ALL PRIVILEGES ON \`%s\`.* TO '%s'@'%%'; " .
                "FLUSH PRIVILEGES;",
                $o['name'],
                $o['name'],
                $o['user']
            );
            try {
                $mysql_cmd = $this->getMysqlCommand($host_config, $context, 'mysql', $o);
                $mysql_cmd[] = $cmd;
                $shell->run(implode(' ', $mysql_cmd), false, true);
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

        $cmd = array_merge(
            $this->getMysqlCommand($host_config, $context, 'mysqlDump', $data),
            [
                "--no-data",
                "|",
                "#!grep ^DROP",
                "|",
            ],
            $this->getMysqlCommand($host_config, $context, 'mysql', $data)
        );

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
        $data = $this->getDatabaseCredentials($host_config, $context);

        $context->io()->comment(sprintf('Dumping database of `%s` ...', $host_config->getConfigName()));
        $shell->pushWorkingDir($data['workingDir']);

        $cmd = $this->getMysqlCommand($host_config, $context, 'mysqlDump', $data);

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
        bool $drop_db = false
    ): CommandResult {
        $data = $this->getDatabaseCredentials($host_config, $context);

        $shell->pushWorkingDir($data['workingDir']);

        if ($drop_db) {
            $this->dropDatabase($host_config, $context, $shell, $data);
        }

        $this->logger->notice(sprintf('Restoring db from %s ...', $file));

        $cmd = $this->getMysqlCommand($host_config, $context, 'mysql', $data);

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
        $cmd = $this->getMysqlCommand($host_config, $context, 'mysqlAdmin', $host_config['database']);
        $cmd[] = 'ping';
        return $shell->run(implode(' ', $cmd), true, false);
    }


    /**
     * @param array $data
     * @param \Phabalicious\Validation\ValidationErrorBag $errors
     * @param false $validate_working_dir
     */
    public function validateCredentials(array $data, ValidationErrorBag $errors, bool $validate_working_dir = false)
    {
        $service = new ValidationService($data, $errors, 'database');
        $service->hasKeys([
            'host' => 'the database-host',
            'user' => 'the database user',
            'pass' => 'the password for the database-user',
            'name' => 'the database name to use',
        ]);
        if ($validate_working_dir) {
            $service->hasKey('workingDir', 'working dir is missing!');
        }
    }

    public static function createCredentialsUrlForDrupal(array $o): string
    {
        return sprintf(
            '%s://%s:%s@%s/%s',
            $o['driver'] ?? self::METHOD_NAME,
            $o['user'],
            $o['pass'],
            $o['host'],
            $o['name']
        );
    }

    private function getMysqlCommand(
        HostConfig $hostConfig,
        TaskContextInterface $context,
        string $command,
        array $data
    ): array {
        $cmd = [
            "#!" . strtolower($command),
            ];
        $config_key = $command . "Options";

        $options = $context->getConfigurationService()->getSetting(
            $config_key,
            $hostConfig->get($config_key, [])
        );

        return array_merge(
            $cmd,
            $options,
            [
                "-u",
                $data['user'],
                sprintf("-p'%s'", $data['pass']),
                "-h",
                $data["host"],
                "--add-drop-table",
                $data['name'],
            ]
        );
    }
}
