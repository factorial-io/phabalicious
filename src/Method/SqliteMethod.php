<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

class SqliteMethod extends DatabaseMethod implements MethodInterface
{
    public const METHOD_NAME = 'sqlite';

    public function getName(): string
    {
        return self::METHOD_NAME;
    }

    public function supports(string $method_name): bool
    {
        return self::METHOD_NAME === $method_name;
    }

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        return new Node([
            'executables' => [
                'sqlite3' => 'sqlite3',
                'gunzip' => 'gunzip',
                'gzip' => 'gzip',
                'cat' => 'cat',
            ],
        ], $this->getName().' global settings');
    }

    /**
     * @throws \Exception
     */
    public function install(HostConfig $host_config, TaskContextInterface $context): ?CommandResult
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);

        // TODO: implement installation.

        return null;
    }

    public function dropDatabase(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        array $data,
    ): CommandResult {
        return $shell->run(sprintf('rm %s', $data['database']));
    }

    /**
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function exportSqlToFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $backup_file_name,
    ): string {
        $data = $this->getDatabaseCredentials($host_config, $context);

        $context->io()->comment(sprintf('Dumping database of `%s` ...', $host_config->getConfigName()));
        $shell->pushWorkingDir($data['workingDir']);

        $cmd = [
            '#!sqlite3',
            $data['database'],
            '.dump',
        ];

        if (!$shell->exists(dirname($backup_file_name))) {
            $shell->run(sprintf('mkdir -p %s', dirname($backup_file_name)));
        }

        $zipped_backup = $host_config['supportsZippedBackups'];

        if ($zipped_backup) {
            $backup_file_name .= '.gz';
            $cmd[] = '| #!gzip';
        }
        $shell->run(sprintf('rm -f %s', escapeshellarg($backup_file_name)));

        $cmd[] = '>';
        $cmd[] = $backup_file_name;

        $shell->run(implode(' ', $cmd), RunOptions::NONE, true);

        $shell->popWorkingDir();

        return $backup_file_name;
    }

    /**
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function importSqlFromFile(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file,
        bool $drop_db,
    ): CommandResult {
        $data = $this->getDatabaseCredentials($host_config, $context);

        $shell->pushWorkingDir($data['workingDir']);

        if ($drop_db) {
            $this->dropDatabase($host_config, $context, $shell, $data);
        }

        $this->logger->notice(sprintf('Restoring db from %s ...', $file));

        $cmd = [
            '#!sqlite3',
            $data['database'],
        ];

        if ('gz' == substr($file, strrpos($file, '.') + 1)) {
            array_unshift($cmd, '#!gunzip', '-c', $file, '|');
        } else {
            array_unshift($cmd, '#!cat', $file, '|');
        }
        $result = $shell->run(implode(' ', $cmd));
        $shell->popWorkingDir();

        return $result;
    }

    public function checkDatabaseConnection(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
    ): CommandResult {
        // This is a no op.
        return new CommandResult(0, []);
    }

    /**
     * @param false $validate_working_dir
     */
    public function validateCredentials(array $data, ValidationErrorBag $errors, bool $validate_working_dir = false): void
    {
        $service = new ValidationService($data, $errors, 'database');
        $service->hasKeys([
            'database' => 'the path to the sqlite-database on your file-system',
        ]);
        if ($validate_working_dir) {
            $service->hasKey('workingDir', 'working dir is missing!');
        }
    }

    public static function createCredentialsUrlForDrupal(array $o): string
    {
        return sprintf(
            '%s://%s',
            $o['driver'] ?? self::METHOD_NAME,
            $o['database']
        );
    }

    public function getShellCommand(HostConfig $host_config, TaskContextInterface $context): array
    {
        $data = $this->getDatabaseCredentials($host_config, $context);

        return [
            '#!sqlite3',
            $data['database'],
        ];
    }
}
