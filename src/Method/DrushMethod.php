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
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Output\OutputInterface;

class DrushMethod extends BaseMethod implements MethodInterface
{

    const CONFIGURATION_EXISTS = 'configurationExists';
    const CONFIGURATION_USED = 'configurationUsed';
    const SKIP_NEXT_CONFIGURATION_IMPORT = 'skipNextConfigurationImport';
    const SETTINGS_FILE_EXISTS = 'settingsFileExists';

    const LAX_ERROR_HANDLING = 'lax';
    const STRICT_ERROR_HANDLING = 'strict';

    public function getName(): string
    {
        return 'drush';
    }

    public function supports(string $method_name): bool
    {
        return (in_array($method_name, ['drush', 'drush7', 'drush8', 'drush9']));
    }

    public function getMethodDependencies(MethodFactory $factory, array $data): array
    {
        // Check if there is already a database methods declared in `needs`.
        $db_methods = $factory->getSubsetImplementing($data['needs'], DatabaseMethodInterface::class);
        if (!empty($db_methods)) {
            return [];
        }

        return [
            $data['database']['driver'] ?? MysqlMethod::METHOD_NAME,
        ];
    }

    public function getGlobalSettings(): array
    {
        return [
            'adminUser' => 'admin',
            'executables' => [
                'drush' => 'drush',
                'grep' => 'grep',
                'gunzip' => 'gunzip',
                'chmod' => 'chmod',
                'sed' => 'sed',
            ],
            'sqlSkipTables' => [
                'cache',
                'cache_block',
                'cache_bootstrap',
                'cache_field',
                'cache_filter',
                'cache_form',
                'cache_menu',
                'cache_page',
                'cache_path',
                'cache_update',
                'cache_views',
                'cache_views_data',
            ],
            'revertFeatures' => true,
            'replaceSettingsFile' => true,
            'alterSettingsFile' => true,
            'configurationManagement' => [
                'sync' => [
                    '#!drush config-import -y sync'
                ],
            ],
            'installOptions' => [
                'distribution' => 'minimal',
                'locale' => 'en',
                'options' => '',
            ]
        ];
    }

    public function getKeysForDisallowingDeepMerge(): array
    {
        return ['configurationManagement'];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $config = parent::getDefaultConfig($configuration_service, $host_config);

        $keys = ['adminUser',
            'revertFeatures',
            'alterSettingsFile',
            'replaceSettingsFile',
            'configurationManagement',
            'installOptions'];
        foreach ($keys as $key) {
            $config[$key] = $configuration_service->getSetting($key);
        }

        $config['adminPass'] = $configuration_service->getSetting(
            'adminPass',
            base64_encode('!admin%' . ($host_config['config_name'] ?? 'whatever') . '4')
        );
        if (isset($host_config['database'])) {
            $config['database']['host'] = 'localhost';
            $config['database']['skipCreateDatabase'] = false;
            $config['database']['prefix'] = false;
        }

        $config['drupalVersion'] = in_array('drush7', $host_config['needs'])
            ? 7
            : $configuration_service->getSetting('drupalVersion', 8);

        $config['drushVersion'] = in_array('drush9', $host_config['needs'])
            ? 9
            : $configuration_service->getSetting('drushVersion', 8);

        $config['sanitizeOnReset'] = false;
        $config['supportsZippedBackups'] = true;
        $config['siteFolder'] = '/sites/default';
        $config['filesFolder'] = '/sites/default/files';
        $config['configBaseFolder'] = '../config';
        $config['forceConfigurationManagement'] = false;
        $config['drushErrorHandling'] = self::STRICT_ERROR_HANDLING;

        return $config;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors); // TODO: Change the autogenerated stub

        $service = new ValidationService($config, $errors, sprintf('host: `%s`', $config['configName']));

        $service->hasKey('drushVersion', 'the major version of the installed drush tool');
        $service->hasKey('drupalVersion', 'the major version of the drupal-instance');
        $service->hasKey('siteFolder', 'drush needs a site-folder to locate the drupal-instance');
        $service->hasKey('filesFolder', 'drush needs to know where files are stored for this drupal instance');
        $service->hasKey('tmpFolder', 'drush needs to know where to store temporary files');

        $service->isOneOf('drushErrorHandling', [ self::STRICT_ERROR_HANDLING, self::LAX_ERROR_HANDLING]);

        if (array_intersect($config['needs'], ['drush7', 'drush8', 'drush9'])) {
            $errors->addWarning(
                'needs',
                '`drush7`, `drush8` and `drush9` are deprecated, ' .
                'please replace with `drush` and set `drupalVersion` and `drushVersion` accordingly.'
            );
        }

        if (!empty($config['sqlDumpCommand'])) {
            $errors->addWarning('sqlDumpCommand', '`sqlDumpCommand` is not supported anymore!');
        }
    }

    /**
     * @param ConfigurationService $configuration_service
     * @param array $data
     * @throws ValidationFailedException
     */
    public function alterConfig(ConfigurationService $configuration_service, array &$data)
    {
        parent::alterConfig($configuration_service, $data);

        $data['siteFolder'] = Utilities::prependRootFolder($data['rootFolder'], $data['siteFolder']);
        $data['filesFolder'] = Utilities::prependRootFolder($data['rootFolder'], $data['filesFolder']);

        // Late validation of uuid + drupal 8+.
        if ($data['drupalVersion'] >= 8 && !$configuration_service->getSetting('uuid')) {
            $errors = new ValidationErrorBag();
            $errors->addError('global', 'Drupal 8 needs a global uuid-setting');
            throw new ValidationFailedException($errors);
        }
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        return parent::isRunningAppRequired($host_config, $context, $task) ||
            in_array($task, [
            'drush',
            'install',
            'deploy',
            'reset',
            'appUpdate',
            'variables',
            'requestDatabaseCredentialsAndWorkingDir',
        ]);
    }

    private function useStrictErrorHandling(HostConfig $host_config): bool
    {
        $drush_error_handling = $host_config->get('drushErrorHandling', self::LAX_ERROR_HANDLING);
        return $drush_error_handling == self::STRICT_ERROR_HANDLING;
    }

    private function runDrush(ShellProviderInterface $shell, bool $throw_exception_on_failure, $cmd, ...$args)
    {
        array_unshift($args, '#!drush ' . $cmd);
        $command = call_user_func_array('sprintf', $args);
        return $shell->run(
            $command,
            false,
            $throw_exception_on_failure && $this->useStrictErrorHandling($shell->getHostConfig())
        );
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     *
     * @throws MethodNotFoundException
     * @throws MissingScriptCallbackImplementation
     * @throws UnknownReplacementPatternException
     * @throws \Phabalicious\Exception\FailedShellCommandException
     */
    public function reset(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);
        $shell->cd($host_config['siteFolder']);

        /** @var ScriptMethod $script_method */
        $script_method = $context->getConfigurationService()->getMethodFactory()->getMethod('script');

        if ($host_config->get('sanitizeOnReset', false)) {
            $this->runDrush($shell, true, 'sql-sanitize -y');
        }

        if ($deployment_module = $context->getConfigurationService()->getSetting('deploymentModule')) {
            $this->runDrush($shell, false, 'en -y %s', $deployment_module);
        }

        $this->handleModules($host_config, $context, $shell, 'modules_enabled.txt', true);
        $this->handleModules($host_config, $context, $shell, 'modules_disabled.txt', false);

        // Database updates
        if ($host_config['drupalVersion'] >= 8) {
            $this->runDrush($shell, true, 'updb -y --no-cache-clear');
            $this->runDrush($shell, true, 'cr -y');
        } else {
            $this->runDrush($shell, false, 'updb -y ');
        }

        // CMI / Features
        if ($host_config['drupalVersion'] >= 8) {
            $uuid = $context->getConfigurationService()->getSetting('uuid');
            $this->runDrush($shell, true, 'cset system.site uuid %s -y', $uuid);
            $this->checkExistingInstall($shell, $host_config, $context);

            if (!empty($host_config['configurationManagement'])
                && $context->getResult(self::CONFIGURATION_EXISTS)
                && !$context->getResult(self::SKIP_NEXT_CONFIGURATION_IMPORT, false)
            ) {
                $script_context = clone $context;
                foreach ($host_config['configurationManagement'] as $key => $cmds) {
                    $script_context->set(ScriptMethod::SCRIPT_DATA, $cmds);
                    $script_context->set('rootFolder', $host_config['siteFolder']);
                    $script_method->runScript($host_config, $script_context);

                    /** @var CommandResult $result */
                    $result = $script_context->getResult('commandResult', false);
                    if ($result && $result->failed()) {
                        $result->throwException("Could not import configuration");
                    }
                }
            }
        } else {
            if ($host_config['revertFeatures']) {
                $this->runDrush($shell, false, 'fra -y');
            }
        }

        $context->set('rootFolder', $host_config['siteFolder']);
        $script_method->runTaskSpecificScripts($host_config, 'reset', $context);

        // Keep calm and clear the cache.
        if ($host_config['drupalVersion'] >= 8) {
            $this->runDrush($shell, true, 'cr -y');
            $this->runDrush($shell, true, sprintf('state-set installation_type %s', $host_config['type']));
            $this->runDrush($shell, true, sprintf('state-set phab_config_name "%s"', $host_config->getConfigName()));
            $this->runDrush($shell, true, sprintf(
                'state-set installation_name "%s"',
                $context->getConfigurationService()->getSetting('name')
            ));
        } else {
            $this->runDrush($shell, true, 'cc all -y');
        }
        if ($host_config['drushVersion'] >= 10) {
            $this->runDrush($shell, true, 'deploy:hook -y');
        }

        // Set admin password for dev-instances.
        if ($host_config->isType(HostType::DEV)) {
            $admin_user = $host_config['adminUser'];
            $admin_pass = $host_config['adminPass'];

            if ($context->get('withPasswordReset', true)) {
                if ($host_config['drushVersion'] >= 9) {
                    $command = sprintf('user:password %s "%s"', $admin_user, $admin_pass);
                } else {
                    $command = sprintf('user-password %s --password="%s"', $admin_user, $admin_pass);
                }
                $this->runDrush($shell, true, $command);
            }

            $shell->run(sprintf('chmod -R 777 %s', $host_config['filesFolder']));
        }
    }

    public function drush(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');

        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);
        $shell->cd($host_config['siteFolder']);
        $context->setResult('shell', $shell);
        $command = sprintf(
            'cd %s;  #!drush  %s',
            $host_config['siteFolder'],
            $command
        );
        $command = $shell->expandCommand($command);
        $context->setResult('command', [
            $command
        ]);
    }

    private function handleModules(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file_name,
        bool $should_enable
    ) {
        $file = $host_config['rootFolder'] . '/' . $file_name;
        if (!$shell->exists($file)) {
            return;
        }
        $content = $shell->run('cat ' . $file, true);

        $modules = array_filter($content->getOutput(), 'trim');
        $key = $should_enable ? 'modulesEnabledIgnore' : 'modulesDisabledIgnore';

        $to_ignore = $context->getConfigurationService()->getSetting($key, []);
        if (count($to_ignore) > 0) {
            $this->logger->notice(sprintf(
                'Ignoring %s while %s modules from %s',
                implode(' ', $to_ignore),
                $should_enable ? 'enabling' : 'disabling',
                $host_config['configName']
            ));

            $modules = array_diff($modules, $to_ignore);
        }
        $drush_command = ($should_enable) ? 'en -y %s' : 'dis -y %s';

        if (!$context->getOutput() || ($context->getOutput()->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE)) {
            $modules = [
                implode(' ', $modules),
            ];
        }

        foreach ($modules as $module) {
            $result = $this->runDrush($shell, false, $drush_command, $module);
            if ($result->failed()) {
                $result->throwException(sprintf(
                    'Drush reported an error while handling %s and module %s. ' .
                    'Please check the output, there might be an error in the file.',
                    $file_name,
                    $module
                ));
            }
        }
    }

    public function install(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $this->getShell($host_config, $context);

        // Determine what kind of install operation this will be.
        $this->checkExistingInstall($shell, $host_config, $context);

        $shell->cd($host_config['rootFolder']);
        $shell->run(sprintf('mkdir -p %s', $host_config['siteFolder']));

        // Create DB.
        $shell->cd($host_config['siteFolder']);
        $o = $host_config['database'] ?? false;

        // Prepare settings.php
        $shell->run(sprintf('#!chmod u+w %s', $host_config['siteFolder']));

        if ($context->getResult(self::SETTINGS_FILE_EXISTS)) {
            $shell->run(sprintf('#!chmod u+w %s/settings.php', $host_config['siteFolder']));
            if ($host_config['replaceSettingsFile'] && !$context->getResult(self::CONFIGURATION_USED)) {
                $shell->run(sprintf('rm -f %s/settings.php.old', $host_config['siteFolder']));
                $shell->run(sprintf(
                    'mv %s/settings.php %s/settings.php.old 2>/dev/null',
                    $host_config['siteFolder'],
                    $host_config['siteFolder']
                ));
            }
        }

        $install_options = [
            '-y',
            '--sites-subdir=' . basename($host_config['siteFolder']),
            '--account-name=' . $host_config['adminUser'],
            sprintf('--account-pass="%s"', $host_config['adminPass']),
            $host_config['installOptions']['options'],
        ];

        if ($o) {
            if ($host_config['database']['prefix']) {
                $install_options[] = ' --db-prefix=' . $host_config['database']['prefix'];
            }

            switch ($o['driver'] ?? 'mysql') {
                case 'sqlite':
                    $db_url = SqliteMethod::createCredentialsUrlForDrupal($o);
                    break;
                default:
                    $db_url = MysqlMethod::createCredentialsUrlForDrupal($o);
                    break;
            }
            $install_options[] = sprintf('--db-url=%s', $db_url);
        }

        // Install drupal, this can be skipped if install from configuration is
        // possible.
        if ($context->getResult(self::CONFIGURATION_USED)) {
            $this->logger->info('Found existing and used config, installing from it ...');

            $install_options[] = '--existing-config';
            $context->setResult(self::SKIP_NEXT_CONFIGURATION_IMPORT, true);
        } else {
            $this->logger->info('Installing distribution '. $host_config['installOptions']['distribution']);

            $install_options[] = '--locale=' . $host_config['installOptions']['locale'];
            $install_options[] = $host_config['installOptions']['distribution'];
        }

        $result = $this->runDrush($shell, false, 'site-install %s', implode(' ', $install_options));
        if ($result->failed()) {
            $result->throwException("Drupal installation failed!");
        }

        $this->setupConfigurationManagement($host_config, $context);
    }


    public function appUpdate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (in_array('composer', $host_config['needs'])) {
            // Project is handled by composer, will handle the update.
            return;
        }


        $this->logger->notice('Updating drupal core');
        $shell = $this->getShell($host_config, $context);
        $pwd = $shell->getWorkingDir();
        $install_dir = $host_config['tmpFolder'] . '/drupal-update';
        $shell->run(sprintf('rm -rf %s', $install_dir));
        $shell->run(sprintf('mkdir -p %s', $install_dir));
        $result = $this->runDrush(
            $shell,
            true,
            'dl --destination="%s" --default-major="%d" drupal',
            $install_dir,
            $host_config['drupalVersion']
        );

        if ($result->failed()) {
            throw new \RuntimeException('Could not download drupal via drush!');
        }

        $shell->cd($install_dir);
        $result = $shell->run('ls ', true);
        $drupal_folder = trim($result->getOutput()[0]);
        $shell->run(sprintf('#!rsync -rav --no-o --no-g %s/* %s', $drupal_folder, $host_config['rootFolder']));
        $shell->run(sprintf('rm -rf %s', $install_dir));

        $shell->cd($pwd);
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws FailedShellCommandException
     */
    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }
        if ($current_stage === 'install') {
            $this->install($host_config, $context);
        }
    }

    private function getConfigSyncDirectory(HostConfig $host_config)
    {
        return implode('/', [
            $host_config['rootFolder'],
            $host_config['configBaseFolder'],
            array_key_last($host_config['configurationManagement']),
        ]);
    }

    private function setupConfigurationManagement(HostConfig $host_config, TaskContextInterface $context)
    {
        if ($host_config['drupalVersion'] < 8
            || empty($host_config['alterSettingsFile'])
            || $context->getResult(self::CONFIGURATION_USED, false)
        ) {
            return;
        }

        $shell = $this->getShell($host_config, $context);
        $cwd = $shell->getWorkingDir();
        $shell->cd($host_config['siteFolder']);
        $shell->run('#!chmod u+w .');
        $shell->run('#!chmod u+w settings.php');

        $shell->run('#!sed -i "/\$config_directories\[/d" settings.php');
        $shell->run('#!sed -i "/\$settings\[\'config_sync_directory/d" settings.php');
        foreach ($host_config['configurationManagement'] as $key => $data) {
            $shell->run(sprintf(
                'echo "\$settings[\'config_sync_directory\'] = \'%s\';" >> settings.php',
                '../config/' . $key
            ));
        }

        $shell->cd($cwd);
    }

    public function variables(HostConfig $host_config, TaskContextInterface $context)
    {
        $result = $context->get('data', []);
        $what = $context->get('action', 'pull');

        $context->io()->progressStart(count($result));

        foreach ($result as $key => $value) {
            if ($what == 'pull') {
                $this->logger->info(sprintf('Pulling `%s` from `%s`', $key, $host_config->getConfigName()));
                $result[$key] = $this->getVariable($host_config, $context, $key);
            } elseif ($what == 'push' && !is_null($value)) {
                $this->logger->info(sprintf('Pushing `%s` to `%s`', $key, $host_config->getConfigName()));
                $this->putVariable($host_config, $context, $key, $value);
            }
            $context->io()->progressAdvance();
        }
        $context->io()->progressFinish();
        $context->setResult('data', $result);
    }

    private function getVariable(HostConfig $host_config, TaskContextInterface $context, $key)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $host_config->shell());
        if ($host_config['drupalVersion'] == 7) {
            $shell->pushWorkingDir($host_config['siteFolder']);
            $output = $shell->run(sprintf('#!drush variable-get --format=json %s', $key), true, false);
            $shell->popWorkingDir();
            if ($output->failed()) {
                if (!empty($output->getOutput())) {
                    $this->logger->error(implode("\n", $output->getOutput()));
                }
                return null;
            }
            $json = json_decode(implode("\n", $output->getOutput()), true);
            return isset($json[$key]) ? $json[$key] : null;
        }

        throw new \InvalidArgumentException('getVariable is not implemented for that particular drupal version.');
    }

    private function putVariable(HostConfig $host_config, TaskContextInterface $context, $key, $value)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $host_config->shell());
        if ($host_config['drupalVersion'] == 7) {
            $shell->pushWorkingDir($host_config['siteFolder']);
            $output = $shell->run(sprintf(
                '#!drush variable-set --yes --format=json %s \'%s\'',
                $key,
                json_encode($value)
            ), true);
            $shell->popWorkingDir();
            if ($output->failed()) {
                throw new \RuntimeException($output->getOutput());
            }
            return;
        }
        throw new \InvalidArgumentException('putVariable is not implemented for that particular drupal version.');
    }

    private function checkExistingInstall(
        ShellProviderInterface $shell,
        HostConfig $host_config,
        TaskContextInterface $context
    ) {
        $shell->pushWorkingDir($host_config['siteFolder']);
        $settings_file_exists = $shell->exists($host_config['siteFolder'] . '/settings.php');
        $config_dir_exists = $shell->exists($this->getConfigSyncDirectory($host_config) . '/core.extension.yml');
        $config_used = false;

        if ($settings_file_exists) {
            $result = $shell->run(
                sprintf(
                    '#!grep -q "^\$settings\[\'config_sync_directory\'] = \'%s/" settings.php',
                    $host_config["configBaseFolder"]
                ),
                true,
                false
            );

            $config_used = $host_config['forceConfigurationManagement'] || ($result->succeeded() && $config_dir_exists);
        }
        $shell->popWorkingDir();

        $supported_by_drush = $host_config['drushVersion'] >= 9;

        $this->logger->debug(sprintf("Settings file exists: %s", $settings_file_exists ? "TRUE" : "FALSE"));
        $this->logger->debug(sprintf(
            "Configuration dir %s exists: %s",
            $this->getConfigSyncDirectory($host_config),
            $config_dir_exists ? "TRUE" : "FALSE"
        ));
        $this->logger->debug(sprintf("Configuration used: %s", $config_used ? "TRUE" : "FALSE"));
        $this->logger->debug(sprintf("Drush supports config import: %s", $supported_by_drush ? "TRUE" : "FALSE"));

        $context->setResult(self::SETTINGS_FILE_EXISTS, $settings_file_exists);
        $context->setResult(self::CONFIGURATION_EXISTS, $config_dir_exists);
        $context->setResult(
            self::CONFIGURATION_USED,
            $config_dir_exists && $config_used && $supported_by_drush
        );
    }

    /**
     * @param \Phabalicious\Configuration\HostConfig $host_config
     * @param \Phabalicious\Method\TaskContextInterface $context
     */
    public function requestDatabaseCredentialsAndWorkingDir(HostConfig $host_config, TaskContextInterface  $context)
    {
        $data = $context->get(DatabaseMethod::DATABASE_CREDENTIALS, []);
        if (empty($data)) {
            $sql_conf_cmd = ($host_config['drushVersion'] < 9) ? 'sql-conf' : 'sql:conf';
            /** @var ShellProviderInterface $shell */
            $shell = $context->get('shell', $host_config->shell());
            $shell->pushWorkingDir($host_config['siteFolder']);
            $result = $shell->run('drush --show-passwords --format=json ' . $sql_conf_cmd, true, false);
            $json = json_decode(implode("\n", $result->getOutput()), true);
            $defaults = [
                'mysql' => [
                    'host' => 'localhost',
                    'port' => '3306'
                ],
                'sqlite' => [],
            ];
            $mapping = [
                'mysql' => [
                    "database" => "name",
                    "username" => "user",
                    "password" => "pass",
                    "host" => "host",
                    "port" => "port",
                ],
                'sqlite' => [
                    "database" => "database",
                ],
            ];
            foreach ($mapping[$json['driver']] as $key => $mapped) {
                $data[$mapped] = $json[$key] ?: $defaults[$json['driver']][$key] ?? '';
            }
            $data['driver'] = $json['driver'];
        }
        switch ($data['driver']) {
            case 'sqlite':
                $context
                    ->io()
                    ->warning(
                        "Drupal is using custom collation types which are not supported by sqlite3. " .
                        "YMMV! See https://www.drupal.org/project/drupal/issues/3036487"
                    );
                $data['workingDir'] = $host_config['rootFolder'];
                break;

            default:
                $data['workingDir'] = $host_config['siteFolder'];
                break;
        }
        $context->setResult(DatabaseMethod::DATABASE_CREDENTIALS, $data);
    }
}
