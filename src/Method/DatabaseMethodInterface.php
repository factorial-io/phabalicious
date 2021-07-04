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
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     *
     * @return mixed
     */
    public function install(HostConfig $host_config, TaskContextInterface $context);

    /**
     * Export current database into a sql file.
     *
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
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
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
     * @param string $file
     * @param false $drop_db
     *
     * @return \Phabalicious\ShellProvider\CommandResult
     */
    public function importSqlFromFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file,
        bool $drop_db = false
    ): CommandResult;

    /**
     * Drop all tables in the database.
     *
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
     * @param array $data
     *
     * @return mixed
     */
    public function dropDatabase(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data
    );

    /**
     * Check if database can handle connections.
     *
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
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
     * @param \Phabalicious\Validation\ValidationErrorBag $errors
     * @param false $validate_working_dir
     *
     * @return mixed
     */
    public function validateCredentials(array $data, ValidationErrorBag $errors, bool $validate_working_dir = false);
}
