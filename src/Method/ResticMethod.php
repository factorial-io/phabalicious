<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\HostType;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\EnsureKnownHosts;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Output\OutputInterface;

class ResticMethod extends BaseMethod implements MethodInterface
{


    public function getName(): string
    {
        return 'restic';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == $this->getName();
    }

    public function getGlobalSettings(): array
    {
        return [
            'executables' => [
                'restic' => 'restic',
                'curl' => 'curl',
                'bunzip2' => 'bunzip2',
            ],
            'restic' => [
                'options' => [
                    '--verbose'
                ],
                'allowInstallation' => true,
                'environment' => [],
                'downloadUrl' =>
                    'https://github.com/restic/restic/releases/download/v0.12.0/restic_0.12.0_linux_amd64.bz2',
            ],
        ];
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $config = parent::getDefaultConfig($configuration_service, $host_config);
        $config = Utilities::mergeData([
            'fileBackupStrategy' => 'restic',
            'restic' => $configuration_service->getSetting('restic')
            ], $config);
        return $config;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);

        $service = new ValidationService(
            $config,
            $errors,
            sprintf('host: `%s.restic`', $config['configName'])
        );
        $service->hasKey('restic', 'COuld not find restic config');

        if (isset($config['restic'])) {
            $service = new ValidationService(
                $config['restic'],
                $errors,
                sprintf('host: `%s.restic`', $config['configName'])
            );

            $service->hasKeys([
                'repository' => 'The repository to backup to',
                'environment' => 'The environment variables to apply before calling restic'
            ]);
        }
    }


    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        return parent::isRunningAppRequired($host_config, $context, $task) ||
            in_array($task, [
                'deploy',
                'backup',
                'restore',
                'listBackups',
            ]);
    }

    public function backup(HostConfig $host_config, TaskContextInterface $context)
    {
        if ($host_config->get('fileBackupStrategy', 'files') !== 'restic') {
            return;
        }

        $what = $context->get('what', []);
        if (!in_array('restic', $what)) {
            return;
        }

        $shell = $this->getShell($host_config, $context);
        $shell->applyEnvironment($host_config['restic']['environment']);


        $keys = $context->get('backupFolderKeys', []);
        $keys = array_merge($keys, FilesMethod::DEFAULT_FILE_SOURCES);

        foreach ($keys as $key) {
            if (!empty($host_config[$key])) {
                $folder = $host_config[$key];
                $context->addResult('files', [[
                    'type' => 'restic',
                    'file' => $folder
                ]]);
            }
        }

        // We are skipping the real backup and run it in backupFinished.
    }

    public function backupFinished(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $shell->applyEnvironment($host_config['restic']['environment']);

        $repository = $host_config['restic']['repository'];

        if (substr($repository, 0, 5) == 'sftp:') {
            EnsureKnownHosts::ensureKnownHosts(
                $context->getConfigurationService(),
                $this->getKnownHosts($host_config, $context),
                $shell
            );
        }
        $restic_path = $this->ensureResticExecutable($shell, $host_config, $context);

        $context->io()->comment(sprintf(
            "Running backup to offsite repo `%s`",
            $repository
        ));

        $files = [];
        foreach ($context->getResult('files', []) as $file) {
            if (in_array($file['type'], ['restic', 'db'])) {
                $files[] = $file['file'];
            }
        }

        $this->backupFilesOrFolders($host_config, $context, $shell, $restic_path, $files);
    }

    public function restorePrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $what = $context->get('what', []);
        if (!in_array('restic', $what)) {
            return;
        }
        $shell = $this->getShell($host_config, $context);
        $shell->applyEnvironment($host_config['restic']['environment']);

        $restic_path = $this->ensureResticExecutable($shell, $host_config, $context);

        $backup_set = $context->get('backup_set', []);
        foreach ($backup_set as $elem) {
            if ($elem['type'] != 'restic') {
                continue;
            }
            [$name, $config, $short_id] = explode('--', $elem['hash']);
            $this->restoreFilesAndFolders($host_config, $context, $shell, $restic_path, $short_id);
        }
    }

    private function backupFilesOrFolders(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $restic_path,
        array $files
    ) {
        $options = $this->getResticOptions($host_config, $context);

        foreach ($context->getConfigurationService()->getSetting('excludeFiles.backup', []) as $exclude) {
            $options[] = '--exclude';
            $options[] = $exclude;
        }

        $shell->run(sprintf(
            '%s %s backup %s',
            $restic_path,
            implode(' ', $options),
            implode(' ', $files),
        ), false, true);
    }

    private function restoreFilesAndFolders(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $restic_path,
        string $short_id
    ) {
        $options = $this->getResticOptions($host_config, $context);

        $shell->run(sprintf(
            '%s %s restore %s --target /',
            $restic_path,
            implode(' ', $options),
            $short_id,
        ), false, true);
    }

    private function ensureResticExecutable(
        ShellProviderInterface $shell,
        HostConfig $host_config,
        TaskContextInterface $context
    ) {
        $result = $shell->run('#!restic --help', true);
        if ($result->succeeded()) {
            return '#!restic';
        }

        $restic_path = $host_config['backupFolder'] . '/restic';
        $result = $shell->run(sprintf('%s --help', $restic_path), true);
        if ($result->succeeded()) {
            return $restic_path;
        }

        $context->io()->comment("Could not find restic app, trying to install it from github ...");

        $shell->run(sprintf(
            '#!curl -L %s  | #!bunzip2 > %s',
            $host_config['restic']['downloadUrl'],
            $restic_path
        ), true, true);
        $shell->run(sprintf("chmod +x %s", $restic_path), false, true);

        return $restic_path;
    }

    public function listBackups(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShell($host_config, $context);
        $shell->applyEnvironment($host_config['restic']['environment']);
        $restic_path = $this->ensureResticExecutable($shell, $host_config, $context);

        $options = $this->getResticOptions($host_config, $context);
        $options[] = '--json';

        $result = $shell->run(sprintf("%s %s snapshots", $restic_path, implode(' ', $options)), true, true);
        $json = json_decode(implode(" ", $result->getOutput()));

        $result = [];
        foreach ($json as $files) {
            [$name, $config] = explode("--", $files->hostname);

            $d = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $files->time);
            $tokens = [
                'config' => $config,
                'date' => $d->format('Y-m-d'),
                'time' => $d->format("h:m:s"),
                'type' => 'restic',
                'hash' => implode("--", [$name, $config, $files->short_id]),
                'file' => implode("\n", $files->paths),
            ];
            $result[] = $tokens;
        }

        $context->addResult('files', $result);
    }

    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     *
     * @return array|mixed
     */
    protected function getResticOptions(HostConfig $host_config, TaskContextInterface $context)
    {
        $options = $host_config['restic']['options'] ?? [];
        $options[] = '-r';
        $options[] = $host_config['restic']['repository'];

        $options[] = '--host';
        $options[] = Utilities::slugify(
            $context->getConfigurationService()->getSetting('name', 'unknown'),
            '-'
        ) . '--' . $host_config['configName'];

        return $options;
    }


    public function collectBackupMethods(HostConfig $config, TaskContextInterface $context)
    {
        $context->addResult('backupMethods', ['restic']);
    }
}
