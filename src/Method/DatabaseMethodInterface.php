<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBag;

interface DatabaseMethodInterface
{

    /**
     * Install a new database.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     *
     * @return mixed
     */
    public function install(HostConfig $host_config, TaskContextInterface $context): ?CommandResult;

    /**
     * Export current database into a sql file.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param string $backup_file_name
     *
     * @return string
     */
    public function exportSqlToFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $backup_file_name
    ): string;

    /**
     * Import new data into database from sql dump.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param string $file
     * @param false $drop_db
     *
     * @return CommandResult
     */
    public function importSqlFromFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file,
        bool $drop_db
    ): CommandResult;

    /**
     * Drop all tables in the database.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param array $data
     *
     * @return mixed
     */
    public function dropDatabase(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data
    ): CommandResult;

    /**
     * Check if database can handle connections.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     *
     * @return mixed
     */
    public function checkDatabaseConnection(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell
    );

    /**
     * Validate database credentials.
     *
     * @param array $data
     * @param ValidationErrorBag $errors
     * @param false $validate_working_dir
     *
     * @return mixed
     */
    public function validateCredentials(array $data, ValidationErrorBag $errors, bool $validate_working_dir = false);

    /**
     * Get an url encoding the database credentials for drupal.
     *
     * @param array $database
     *
     * @return string
     */
    public static function createCredentialsUrlForDrupal(array $database): string;

    public function getShellCommand(HostConfig $host_config, TaskContextInterface $context): array;

    /**
     * Run a single query.
     *
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
     * @param array $data
     *
     * @return \Phabalicious\ShellProvider\CommandResult
     */
    public function runQuery(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data
    ): CommandResult;
}
