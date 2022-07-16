<?php

namespace Phabalicious\Method;

use Exception;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

class MysqlMethod extends DatabaseMethod implements MethodInterface
{
    const METHOD_NAME = 'mysql';
    const SUPPORTS_ZIPPED_BACKUPS = 'supportsZippedBackups';

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
     * @return string[][]
     */
    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        return new Node([
            'executables' => [
                'mysql' => 'mysql',
                'grep' => 'grep',
                'mysqladmin' => 'mysqladmin',
                'mysqldump' => 'mysqldump',
                'gunzip' => 'gunzip',
                'gzip' => 'gzip',
                'cat' => 'cat',
            ],
        ], $this->getName() . ' global settings');
    }

    public function getKeysForDisallowingDeepMerge(): array
    {
        return [
            'mysqlOptions',
            'mysqlDumpOptions',
            'mysqlAdminOptions',
        ];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);

        $config = [];

        if (isset($host_config['database'])) {
            $config['database']['host'] = 'localhost';
        }
        foreach ([
            'mysqlAdmin' => [
                '--no-defaults'
            ],
            'mysqlDump' => [
                '--no-defaults'
            ],
            'mysql' => [
                '--no-defaults'
            ],
        ] as $key => $defaults) {
            $option_name = $key . 'Options';
            if (!isset($host_config[$option_name])) {
                $config[$option_name] = $configuration_service->getSetting($option_name, $defaults);
            }
        }

        return $parent->merge(new Node($config, $this->getName() . ' method defaults'));
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     *
     * @throws Exception
     */
    public function install(HostConfig $host_config, TaskContextInterface $context): ?CommandResult
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);

        // Determine what kind of install operation this will be.

        $o = $host_config['database'] ?? false;
        if ($o && empty($host_config['database']['skipCreateDatabase'])) {
            $context->io()->comment(sprintf('Creating database for `%s` if needed...', $host_config->getConfigName()));

            $cmd = sprintf(
                "CREATE DATABASE IF NOT EXISTS `%s`; " .
                "GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'; " .
                "FLUSH PRIVILEGES;",
                $o['name'],
                $o['name'],
                $o['user']
            );
            try {
                $mysql_cmd = $this->getMysqlCommand($host_config, $context, 'mysql', $o, false);
                $mysql_cmd[] = '-e';
                $mysql_cmd[] = escapeshellarg($cmd);
                return $shell->run(implode(' ', $mysql_cmd), false, true);
            } catch (Exception $e) {
                $context->io()
                    ->error("Could not create database, or grant privileges!");
                $context->io()
                    ->comment("Create the db by yourself and set host.database.skipCreateDatabase to true");
                throw ($e);
            }
        }

        return null;
    }


    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param array $data
     */
    public function dropDatabase(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data
    ):CommandResult {
        $this->logger->notice('Dropping all tables from database ...');

        $shell->run('set -o pipefail');
        $filename = Utilities::getTempFileName($host_config, 'drop-tables.sql');

        $cmd = array_merge(
            $this->getMysqlCommand(
                $host_config,
                $context,
                'mysqlDump',
                $data,
                true,
                ['--no-data', '--single-transaction']
            ),
            [
                "|",
                "#!grep ^DROP",
                '>',
                $filename
            ]
        );

        $result = $shell->run(implode(' ', $cmd), true, true);
        if ($result->failed()) {
            $context->io()->warning("Could not drop tables from db!");
        }

        $cmd =array_merge(
            [
                'cat',
                $filename,
                '|'
            ],
            $this->getMysqlCommand($host_config, $context, 'mysql', $data, true)
        );

        $result = $shell->run(implode(" ", $cmd), false, true);
        $shell->run(sprintf('rm %s', $filename));
        $shell->run('set +o pipefail');

        return $result;
    }

    /**
     * @throws TaskNotFoundInMethodException
     * @throws MethodNotFoundException
     * @throws ValidationFailedException
     */
    public function exportSqlToFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $backup_file_name
    ): string {
        $has_zipped_ext = pathinfo($backup_file_name, PATHINFO_EXTENSION) == 'gz';
        $zipped_backup = $host_config[self::SUPPORTS_ZIPPED_BACKUPS] || $has_zipped_ext;
        if ($zipped_backup && $has_zipped_ext) {
            $backup_file_name = substr($backup_file_name, 0, strrpos($backup_file_name, '.'));
        }

        $data = $this->getDatabaseCredentials($host_config, $context);

        $context->io()->comment(sprintf('Dumping database of `%s` ...', $host_config->getConfigName()));
        $shell->pushWorkingDir($data['workingDir']);
        $shell->run('set -o pipefail');

        $get_structure_cmd = $this->getMysqlCommand($host_config, $context, 'mysqlDump', $data, true);
        $get_structure_cmd[] = "--add-drop-table";
        $get_structure_cmd[] = "--no-autocommit";
        $get_structure_cmd[] = "--single-transaction";
        $get_structure_cmd[] = "--opt";
        $get_structure_cmd[] = "-Q";
        $get_structure_cmd[] = "--no-data";
        $get_structure_cmd[] = ">";
        $get_structure_cmd[] = $backup_file_name;

        $get_data_cmd = $this->getMysqlCommand($host_config, $context, 'mysqlDump', $data, true);
        $get_data_cmd[] = "--no-autocommit";
        $get_data_cmd[] = "--single-transaction";
        $get_data_cmd[] = "--opt";
        $get_data_cmd[] = "-Q";
        $get_data_cmd[] = "--no-create-info";

        foreach ($context->getConfigurationService()->getSetting('sqlSkipTables', []) as $table_name) {
            $get_data_cmd[] = sprintf("--ignore-table %s.%s", $data['name'], $table_name);
        }
        $get_data_cmd[] = ">>";
        $get_data_cmd[] = $backup_file_name;

        if (!$shell->exists(dirname($backup_file_name))) {
            $shell->run(sprintf('mkdir -p %s', dirname($backup_file_name)));
        }

        $shell->run(sprintf('rm -f %s', $backup_file_name));


        $shell->run(implode(" ", $get_structure_cmd), false, true);
        $shell->run(implode(" ", $get_data_cmd), false, true);

        if ($zipped_backup) {
            $shell->run(sprintf('#!gzip -f %s', $backup_file_name));
            $backup_file_name .= '.gz';
        }
        $shell->run('set +o pipefail');
        $shell->popWorkingDir();

        return $backup_file_name;
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param string $file
     * @param bool $drop_db
     *
     * @return CommandResult
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     * @throws ValidationFailedException
     */
    public function importSqlFromFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file,
        bool $drop_db
    ): CommandResult {
        $context->getConfigurationService()->getMethodFactory()->runTask("freezeApp", $host_config, $context);
        try {
            $data = $this->getDatabaseCredentials($host_config, $context);

            $shell->pushWorkingDir($data['workingDir']);

            if ($drop_db) {
                $this->dropDatabase($host_config, $context, $shell, $data);
            }

            $this->logger->info(sprintf('Restoring db from %s ...', $file));

            $cmd = $this->getMysqlCommand($host_config, $context, 'mysql', $data, true);

            if (substr($file, strrpos($file, '.') + 1) == 'gz') {
                array_unshift($cmd, "#!gunzip", "-c", $file, "|");
            } else {
                array_unshift($cmd, "#!cat", $file, "|");
            }
            $result = $shell->run(implode(" ", $cmd));
            $shell->popWorkingDir();

            $context->getConfigurationService()->getMethodFactory()->runTask("unfreezeApp", $host_config, $context);

            return $result;
        } catch (\Exception $e) {
            $context->getConfigurationService()->getMethodFactory()->runTask("unfreezeApp", $host_config, $context);
            throw $e;
        }
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     *
     * @return CommandResult
     */
    public function checkDatabaseConnection(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell
    ): CommandResult {
        $credentials = $this->getDatabaseCredentials($host_config, $context);
        $cmd = $this->getMysqlCommand($host_config, $context, 'mysqlAdmin', $credentials, false);
        $cmd[] = 'ping';
        return $shell->run(implode(' ', $cmd), true, false);
    }


    /**
     * @param array $data
     * @param ValidationErrorBag $errors
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
            '%s://%s:\'%s\'@%s:%s/%s',
            $o['driver'] ?? self::METHOD_NAME,
            $o['user'],
            $o['pass'],
            $o['host'],
            $o['port'] ?? '3306',
            $o['name']
        );
    }

    public function getMysqlCommand(
        HostConfig $hostConfig,
        TaskContextInterface $context,
        string $command,
        array $data,
        bool $include_database_arg,
        $additional_args = []
    ): array {
        $cmd = [
            "#!" . strtolower($command),
            ];
        $config_key = $command . "Options";

        $options = $context->getConfigurationService()->getSetting(
            $config_key,
            $hostConfig->get($config_key, [])
        );

        $cmd = array_merge(
            $cmd,
            $options,
            [
                "-u",
                $data['user'],
                "-h",
                $data["host"],
                "--port",
                $data["port"] ?? "3306"
            ],
            !empty($data['pass'])
                ? [ sprintf("-p'%s'", $data['pass'])]
                : []
        );
        foreach ($additional_args as $arg) {
            $cmd[] = $arg;
        }
        if ($include_database_arg) {
            $cmd[] = $data['name'];
        }

        return $cmd;
    }

    public function getShellCommand(HostConfig $host_config, TaskContextInterface $context): array
    {
        $data = $this->getDatabaseCredentials($host_config, $context);
        return $this->getMysqlCommand($host_config, $context, 'mysql', $data, true, []);
    }
}
