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
     */
    public function install(HostConfig $host_config, TaskContextInterface $context): ?CommandResult;

    /**
     * Export current database into a sql file.
     */
    public function exportSqlToFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $backup_file_name,
    ): string;

    /**
     * Import new data into database from sql dump.
     *
     * @param false $drop_db
     */
    public function importSqlFromFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file,
        bool $drop_db,
    ): CommandResult;

    /**
     * Drop all tables in the database.
     */
    public function dropDatabase(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data,
    ): CommandResult;

    /**
     * Check if database can handle connections.
     */
    public function checkDatabaseConnection(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
    );

    /**
     * Validate database credentials.
     */
    public function validateCredentials(array $data, ValidationErrorBag $errors, bool $validate_working_dir = false): void;

    /**
     * Get an url encoding the database credentials for drupal.
     */
    public static function createCredentialsUrlForDrupal(array $database): string;

    public function getShellCommand(HostConfig $host_config, TaskContextInterface $context): array;

    /**
     * Run a single query.
     */
    public function runQuery(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data,
    ): CommandResult;
}
