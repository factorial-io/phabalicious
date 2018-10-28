<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\HostType;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class DrushMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'drush';
    }

    public function supports(string $method_name): bool
    {
        return (in_array($method_name, ['drush', 'drush7', 'drush8', 'drush9']));
    }

    public function getGlobalSettings(): array
    {
        return [
            'adminUser' => 'admin',
            'executables' => [
                'drush' => 'drush',
                'mysql' => 'mysql',
                'grep' => 'grep',
                'mysqladmin' => 'mysqladmin',
                'gunzip' => 'gunzip'
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
            'replaceSettingsFile' =>  true,
            'configurationManagement' => [
                'staging' => [
                    '#!drush config-import -y staging'
                ],
            ],
            'installOptions' => [
                'distribution' => 'minimal',
                'locale' => 'en',
                'options' => '',
            ]
        ];
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $config  = parent::getDefaultConfig($configuration_service, $host_config);

        $keys = ['adminUser', 'revertFeatures', 'replaceSettingsFile', 'configurationManagement', 'installOptions'];
        foreach ($keys as $key) {
            $config[$key] = $configuration_service->getSetting($key);
        }
        if (isset($host_config['database'])) {
            $config['database']['host'] = 'localhost';
            $config['database']['skipCreateDatabase'] = false;
            $config['database']['prefix'] = false;
        }

        $config['drupalVersion'] = in_array('drush7', $host_config['needs']) ? 7 : 8;
        $config['drushVersion'] = in_array('drush9', $host_config['needs']) ? 9 : 8;

        return $config;
    }

    public function validateConfig(array $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors); // TODO: Change the autogenerated stub

        $service = new ValidationService($config, $errors, 'host');

        $service->hasKey('drushVersion', 'the major version of the installed drush tool');
        $service->hasKey('drupalVersion', 'the major version of the drupal-instance');
        $service->hasKey('siteFolder', 'drush needs a site-folder to locate the drupal-instance');
        $service->hasKey('filesFolder', 'drush needs to know where files are stored for this drupal instance');

        if (!empty($config['database'])) {
            $service = new ValidationService($config['database'], $errors, 'host.database');
            $service->hasKeys([
                'host' => 'the database-host',
                'user' => 'the database user',
                'pass' => 'the password for the database-user',
                'name' => 'the database name to use',
            ]);
        }

        if (array_intersect($config['needs'], ['drush7', 'drush8', 'drush9'])) {
            $errors->addWarning(
                'needs',
                '`drush7`, `drush8` and `drush9` are deprecated, ' .
                'please replace with `drush` and set `drupalVersion` and `drushVersion` accordingly.'
            );
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

    private function runDrush(ShellProviderInterface $shell, $cmd, ...$args)
    {
        array_unshift($args, '#!drush ' . $cmd);
        $command = call_user_func_array('sprintf', $args);
        return $shell->run($command);
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    public function reset(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $host_config->shell());
        $shell->cd($host_config['siteFolder']);

        /** @var ScriptMethod $script_method */
        $script_method = $context->getConfigurationService()->getMethodFactory()->getMethod('script');

        if ($host_config->isType(HostType::DEV)) {
            $admin_user = $host_config['adminUser'];

            if ($context->get('withPasswordReset', true)) {
                if ($host_config['drushVersion'] >= 9) {
                    $command = sprintf('user:password %s "admin"', $admin_user);
                } else {
                    $command = sprintf('user-password %s --password="admin"', $admin_user);
                }
                $this->runDrush($shell, $command);
            }

            $shell->run(sprintf('chmod -R 777 %s', $host_config['filesFolder']));
        }

        if ($deployment_module = $context->getConfigurationService()->getSetting('deploymentModule')) {
            $this->runDrush($shell, 'en -y %s', $deployment_module);
        }

        $this->handleModules($host_config, $context, $shell, 'modules_enabled.txt');
        $this->handleModules($host_config, $context, $shell, 'modules_disabled.txt');

        // Database updates
        if ($host_config['drupalVersion'] >= 8) {
            $this->runDrush($shell, 'cr -y');
            $this->runDrush($shell, 'updb --entity-updates -y');
        } else {
            $this->runDrush($shell, 'updb -y ');
        }

        // CMI / Features
        if ($host_config['drupalVersion'] >= 8) {
            $uuid = $context->getConfigurationService()->getSetting('uuid');
            $this->runDrush($shell, 'cset system.site uuid %s -y', $uuid);

            if (!empty($host_config['configurationManagement'])) {
                $script_context = clone $context;
                foreach ($host_config['configurationManagement'] as $key => $cmds) {
                    $script_context->set('scriptData', $cmds);
                    $script_context->set('rootFolder', $host_config['siteFolder']);
                    $script_method->runScript($host_config, $script_context);
                }
            }
        } else {
            if ($host_config['revertFeatures']) {
                $this->runDrush($shell, 'fra -y');
            }
        }

        $script_method->runTaskSpecificScripts($host_config, 'reset', $context);

        // Keep calm and clear the cache.
        if ($host_config['drupalVersion'] >= 8) {
            $this->runDrush($shell, 'cr -y');
        } else {
            $this->runDrush($shell, 'cc all -y');
        }
    }

    public function drush(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');

        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $host_config->shell());
        $shell->cd($host_config['siteFolder']);
        $result = $shell->run('#!drush ' . $command);
        $context->setResult('exitCode', $result->getExitCode());
    }

    private function handleModules(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $file_name
    ) {

    }

    public function install(HostConfig $host_config, TaskContextInterface $context)
    {
        if (empty($host_config['database'])) {
            throw new \InvalidArgumentException('Missing database confiuration!');
        }

        /** @var ShellProviderInterface $shell */
        $shell = $context->get('shell', $host_config->shell());

        $shell->cd($host_config['rootFolder']);
        $shell->run(sprintf('mkdir -p %s', $host_config['siteFolder']));

        // Create DB.
        $shell->cd($host_config['siteFolder']);
        $o = $host_config['database'];
        if (!$host_config['database']['skipCreateDatabase']) {
            $cmd = 'CREATE DATABASE IF NOT EXISTS ' . $o['name'] . '; ' .
                'GRANT ALL PRIVILEGES ON ' . $o['name'] . '.* ' .
                'TO \'' . $o['user'] . '\'@\'%\' ' .
                'IDENTIFIED BY \'' . $o['pass'] . '\';' .
                'FLUSH PRIVILEGES;';
            $shell->run('#!mysql' .
                ' -h ' . $o['host'] .
                ' -u ' . $o['user'] .
                ' --password=' . $o['pass'] .
                ' -e "' . $cmd . '"');
        }

        // Prepare settings.php
        $shell->run(sprintf('#!chmod u+w %s', $host_config['siteFolder']));

        if ($shell->exists($host_config['siteFolder'] . '/settings.php')) {
            $shell->run(sprintf('#!chmod u+w %s/settings.php', $host_config['siteFolder']));
            if ($host_config['replaceSettingsFile']) {
                $shell->run(sprintf('rm -f %s/settings.php.old', $host_config['siteFolder']));
                $shell->run(sprintf(
                    'mv %s/settings.php %s/settings.php.old 2>/dev/null',
                    $host_config['siteFolder'],
                    $host_config['siteFolder']
                ));
            }
        }

        // Install drupal.
        $cmd_options = '';
        $cmd_options .= ' -y';
        $cmd_options .= ' --sites-subdir=' . basename($host_config['siteFolder']);
        $cmd_options .= ' --account-name=' . $host_config['adminUser'];
        $cmd_options .= ' --account-pass=admin';
        $cmd_options .= ' --locale=' . $host_config['installOptions']['locale'];
        if ($host_config['database']['prefix']) {
            $cmd_options .= ' --db-prefix=' . $host_config['database']['prefix'];
        }
        $cmd_options .= ' --db-url=mysql://' . $o['user'] . ':' . $o['pass'] . '@' . $o['host'] . '/' . $o['name'];
        $cmd_options.= ' ' . $host_config['installOptions']['options'];
        $this->runDrush($shell, 'site-install %s %s', $host_config['installOptions']['distribution'], $cmd_options);
    }

}
