<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\BaseShellProvider;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\EnsureKnownHosts;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

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

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        return new Node([
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
        ], $this->getName() . ' global settings');
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $config = [
            'fileBackupStrategy' => 'restic',
            'restic' => $configuration_service->getSetting('restic')
            ];

        return $parent->merge(new Node($config, $this->getName() . ' method defaults'));
    }

    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors
    ) {

        parent::validateConfig($configuration_service, $config, $errors);


        $validation = new ValidationService(
            $config,
            $errors,
            sprintf('host: `%s.restic`', $config['configName'])
        );
        $validation->hasKey('restic', 'Could not find restic config');

        if (isset($config['restic'])) {
            $validation = new ValidationService(
                $config['restic'],
                $errors,
                sprintf('host: `%s.restic`', $config['configName'])
            );

            $validation->hasKeys([
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
                'restic'
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

        $shell = $this->getShellForRestic($host_config, $context);

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
        $shell = $this->getShellForRestic($host_config, $context);

        $repository = $host_config['restic']['repository'];
        $this->ensureKnownHosts($host_config, $context, $shell);
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
        $shell = $this->getShellForRestic($host_config, $context);
        $this->ensureKnownHosts($host_config, $context, $shell);
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
        $options = $this->getResticOptions($host_config, $context, true);

        foreach ($context->getConfigurationService()->getSetting('excludeFiles.backup', []) as $exclude) {
            $options[] = '--exclude';
            $options[] = $exclude;
        }

        $result = $shell->run(sprintf(
            '%s %s backup %s',
            $restic_path,
            implode(' ', $options),
            implode(' ', $files)
        ), false, true);

        if ($result->failed()) {
            $result->throwException("Restic reported an error while trying to run the backup.");
        }
    }

    private function restoreFilesAndFolders(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $restic_path,
        string $short_id
    ) {
        $options = $this->getResticOptions($host_config, $context, true);

        $result = $shell->run(sprintf(
            '%s %s restore %s --target /',
            $restic_path,
            implode(' ', $options),
            $short_id
        ), false);
        if ($result->failed()) {
            $result->throwException("Restic reported an error while trying to restore files.");
        }
    }

    private function ensureResticExecutable(
        ShellProviderInterface $shell,
        HostConfig $host_config,
        TaskContextInterface $context
    ): string {
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
        $shell = $this->getShellForRestic($host_config, $context);
        $restic_path = $this->ensureResticExecutable($shell, $host_config, $context);
        $this->ensureKnownHosts($host_config, $context, $shell);

        $options = $this->getResticOptions($host_config, $context, true);
        $options[] = '--json';

        $result = $shell->run(sprintf("%s %s snapshots", $restic_path, implode(' ', $options)), true);
        if ($result->failed()) {
            $result->throwException("Restic reported an error while trying to get the list of snapshots.");
        }

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
     * @param \Phabalicious\Method\TaskContextInterface $context
     *
     * @return array|mixed
     */
    protected function getResticOptions(HostConfig $host_config, TaskContextInterface $context, $include_host): mixed
    {
        $options = $host_config['restic']['options'] ?? [];
        $options[] = '-r';
        $options[] = $host_config['restic']['repository'];

        if ($include_host) {
            $options[] = '--host';
            $options[] = Utilities::slugify(
                $context->getConfigurationService()
                        ->getSetting('name', 'unknown'),
                '-'
            ) . '--' . $host_config->getConfigName();
        }

        return $options;
    }


    public function collectBackupMethods(HostConfig $config, TaskContextInterface $context)
    {
        $context->addResult('backupMethods', ['restic']);
    }

    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     *
     * @return \Phabalicious\ShellProvider\ShellProviderInterface|null
     */
    protected function getShellForRestic(
        HostConfig $host_config,
        TaskContextInterface $context
    ): ?ShellProviderInterface {
        $shell = $this->getShell($host_config, $context);
        $environment = $host_config['restic']['environment'];
        if ($context->getPasswordManager()) {
            $environment = $context->getPasswordManager()->resolveSecrets($environment);
        }
        $shell->applyEnvironment($environment);
        return $shell;
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @throws \Phabalicious\Exception\FailedShellCommandException
     */
    protected function ensureKnownHosts(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell
    ): void {
        $repository = $host_config['restic']['repository'];
        if (substr($repository, 0, 5) === 'sftp:') {
            $a = Utilities::parseUrl($repository);
            $known_hosts = [
                sprintf("%s:%d", $a['host'], $a['port'] ?? 22)
            ];
            EnsureKnownHosts::ensureKnownHosts($context->getConfigurationService(), $known_hosts, $shell);
        }
    }

    public function restic(HostConfig $host_config, TaskContextInterface $context)
    {
        $shell = $this->getShellForRestic($host_config, $context);
        if (!$shell || !$shell instanceof BaseShellProvider) {
            throw new \RuntimeException("Could not get a shell for restic");
        }

        $repository = $host_config['restic']['repository'];
        $this->ensureKnownHosts($host_config, $context, $shell);
        $restic_path = $this->ensureResticExecutable($shell, $host_config, $context);

        $environment = $host_config['restic']['environment'];
        if ($context->getPasswordManager()) {
            $environment = $context->getPasswordManager()->resolveSecrets($environment);
        }

        $environment_cmds = $shell->getApplyEnvironmentCmds($environment);
        $cmds = [];
        foreach ($environment_cmds as $c) {
            $cmds[] = $c;
            $cmds[] = '&&';
        }
        $cmds[] = $restic_path;
        $command_to_execute = $context->get('command', 'snapshots');
        $cmds = array_merge($cmds, $this->getResticOptions($host_config, $context, false));
        $cmds[] = $command_to_execute;
        $context->setResult('shell', $shell);
        $context->setResult('command', $cmds);
    }
}
