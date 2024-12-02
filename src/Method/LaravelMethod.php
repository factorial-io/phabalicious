<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\ShellProvider\RunOptions;
use Symfony\Component\Dotenv\Dotenv;

class LaravelMethod extends RunCommandBaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'laravel';
    }

    public function getExecutableName(): string
    {
        return "php artisan";
    }

    public function getGlobalSettings(ConfigurationService $configuration): Node
    {
        $parent = parent::getGlobalSettings($configuration);
        $settings = [];
        $settings['artisanTasks'] = [
            'install' => [
                'db:wipe --force',
                'migrate',
                'db:seed'
            ],
            'reset' => [
                'config:cache',
                'migrate',
                'cache:clear'
            ]
        ];

        return $parent->merge(new Node($settings, $this->getName() . ' global settings'));
    }


    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $config = [];
        foreach (['reset', 'install'] as $key) {
            $config['artisanTasks'][$key] =
                $host_config['artisanTasks'][$key] ?? $configuration_service->getSetting("artisanTasks.$key", []);
        }

        return $parent->merge(new Node($config, $this->getName() . ' method defaults'));
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        return parent::isRunningAppRequired($host_config, $context, $task) ||
            in_array($task, [
                'artisan',
                'laravel',
                'install',
                'deploy',
                'reset',
                'requestDatabaseCredentialsAndWorkingDir',
            ]);
    }

    public function artisan(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');
        $this->runCommand($host_config, $context, $command);
    }

    protected function runArtisanTasks(HostConfig $hostConfig, TaskContextInterface $context, string $what)
    {
        $tasks = $hostConfig->get("artisanTasks", []);
        $tasks = $tasks[$what] ?? [];
        foreach ($tasks as $task) {
            $this->runCommand($hostConfig, $context, $task);
        }
    }

    public function install(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runArtisanTasks($host_config, $context, 'install');
    }

    public function reset(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runArtisanTasks($host_config, $context, 'reset');
    }

    public function requestDatabaseCredentialsAndWorkingDir(HostConfig $host_config, TaskContextInterface $context)
    {

        $data = $context->get(DatabaseMethod::DATABASE_CREDENTIALS, []);
        if (empty($data)) {
            /** @var \Phabalicious\ShellProvider\ShellProviderInterface $shell */
            $shell = $context->get('shell', $host_config->shell());
            $shell->pushWorkingDir($host_config['gitRootFolder']);
            $result = $shell->run('cat .env', RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);
            if ($result->failed()) {
                throw new \RuntimeException('Cant get database credentials from laravel installation!');
            }

            $dotenv = new Dotenv();
            $envvars = $dotenv->parse(implode("\n", $result->getOutput()));

            $driver = $data['driver'] = $envvars['DB_CONNECTION'] ?? "mysql";

            $mapping = [
                'mysql' => [
                    'DB_HOST' => 'host',
                    'DB_DATABASE' => 'name',
                    'DB_USERNAME' => 'user',
                    'DB_PASSWORD' => 'pass',
                ],
                'sqlite' => [
                    'DB_DATABASE' => 'database'
                ],
            ];
            foreach ($mapping[$driver] as $key => $mapped) {
                $data[$mapped] = $envvars[$key] ?? false;
            }
            $shell->popWorkingDir();
        }

        $data['workingDir'] = $host_config['rootFolder'];

        $context->setResult(DatabaseMethod::DATABASE_CREDENTIALS, $data);
    }
}
