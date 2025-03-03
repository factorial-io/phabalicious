<?php

/** @noinspection PhpUnusedLocalVariableInspection */

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\DockerConfig;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\FailedShellCommandException;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MismatchedVersionException;
use Phabalicious\Exception\MissingDockerHostConfigException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ScopedLogLevel\ScopedErrorLogLevel;
use Phabalicious\ScopedLogLevel\ScopedLogLevel;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;
use Psr\Log\LogLevel;

class DockerMethod extends BaseMethod implements MethodInterface
{
    use ScaffoldHelperTrait;
    public const METHOD_NAME = 'docker';

    protected array $cache = [];

    protected array $environmentVarsCache = [];

    public function getName(): string
    {
        return self::METHOD_NAME;
    }

    public function supports(string $method_name): bool
    {
        return $method_name === $this->getName();
    }

    public function getDefaultConfig(ConfigurationService $configuration_service, Node $host_config): Node
    {
        $parent = parent::getDefaultConfig($configuration_service, $host_config);
        $config = [
            'docker' => $configuration_service->getSetting('docker', []),
        ];
        $config['executables']['supervisorctl'] = 'supervisorctl';
        $config['executables']['docker-compose'] = 'docker-compose';
        $config['executables']['docker'] = 'docker';
        $config['executables']['chmod'] = 'chmod';
        $config['executables']['chown'] = 'chown';
        $config['executables']['ssh-add'] = 'ssh-add';

        if (!empty($host_config['sshTunnel'])
            && !empty($host_config['docker']['name'])
            && empty($host_config['sshTunnel']['destHostFromDockerContainer'])
            && empty($host_config['sshTunnel']['destHost'])
        ) {
            $config['sshTunnel']['destHostFromDockerContainer'] = $host_config['docker']['name'];
        }
        $config['docker']['scaffold'] = $this->getScaffoldDefaultConfig($host_config, $config, 'docker');

        return $parent->merge(new Node($config, $this->getName().' method defaults'));
    }

    public function validateConfig(
        ConfigurationService $configuration_service,
        Node $config,
        ValidationErrorBagInterface $errors,
    ): void {
        parent::validateConfig($configuration_service, $config, $errors);

        $validation = new ValidationService($config, $errors, sprintf('host: `%s`', $config['configName']));
        $validation->isArray('docker', 'docker configuration needs to be an array');
        if (!$errors->hasErrors()) {
            $validation = new ValidationService(
                $config['docker'],
                $errors,
                sprintf('host.docker: `%s`', $config['configName'])
            );
            if (empty($config['docker']['service'])) {
                $validation->hasKey('name', 'name of the docker-container to inspect');
            }

            $validation->hasKey(
                'projectFolder',
                'projectFolder where the project is stored, relative to the rootFolder'
            );
            $validation->checkForValidFolderName('projectFolder');
            $validation->hasKey('configuration', 'name of the docker-configuration to use');

            $this->validateScaffoldConfig($config, 'docker', $errors);
        }
    }

    public function alterConfig(ConfigurationService $configuration_service, Node $data): void
    {
        if (!empty($data['docker']['service'])) {
            $data->get('docker')->unset('name');
        }
    }

    public function isRunningAppRequired(HostConfig $host_config, TaskContextInterface $context, string $task): bool
    {
        $tasks = $this->getInternalTasks();
        $tasks[] = 'shell';

        return parent::isRunningAppRequired($host_config, $context, $task)
            || in_array($task, $tasks);
    }

    public function getDockerConfig(HostConfig $host_config, TaskContextInterface $context): DockerConfig
    {
        $config = $host_config->getDockerConfig();
        $config['executables'] = $host_config['executables'];
        $environment = $this->environmentVarsCache[$host_config->getConfigName()] ?? false;
        if (!$environment) {
            $environment = $this->getHostEnvironment($host_config, $context, $config);
            $this->environmentVarsCache[$host_config->getConfigName()] = $environment;
        }

        // Override environment.
        $config['environment'] = $environment;

        return $config;
    }

    /**
     * @throws FailedShellCommandException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    public function docker(HostConfig $host_config, TaskContextInterface $context)
    {
        $task = $context->get('docker_task');

        $this->runTaskImpl($host_config, $context, $task.'Prepare', true);
        $this->runTaskImpl($host_config, $context, $task, false);
        $this->runTaskImpl($host_config, $context, $task.'Finished', true);

        // As docker methods might kill the current running application we need to
        // make sure that we terminate the shell.
        $host_config->shell()->terminate();

        $context->io()->success(sprintf('Task `%s` executed successfully!', $task));
    }

    /**
     * @param string $task
     * @param bool   $silent
     *
     * @throws FailedShellCommandException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    private function runTaskImpl(HostConfig $host_config, TaskContextInterface $context, $task, $silent)
    {
        $this->logger->info('Running docker-task `'.$task.'` on `'.$host_config->getConfigName());

        if (method_exists($this, $task)) {
            $this->{$task}($host_config, $context);

            return;
        }

        $docker_config = $this->getDockerConfig($host_config, $context);
        $tasks = $docker_config->get('tasks', []);

        if ($silent && empty($tasks[$task])) {
            return;
        }
        if (empty($tasks[$task])) {
            throw new MethodNotFoundException('Missing task `'.$task.'`');
        }

        $script = $tasks[$task];
        $environment = $docker_config->get('environment', []);
        $callbacks = [];

        /** @var ScriptMethod $method */
        $method = $context->getConfigurationService()->getMethodFactory()->getMethod('script');
        $context->set(ScriptMethod::SCRIPT_DATA, $script);
        $context->mergeAndSet('variables', [
            'dockerHost' => $docker_config->asArray(),
        ]);
        $context->set('environment', $environment);
        $context->set('callbacks', $callbacks);
        $context->set('rootFolder', self::getProjectFolder($docker_config, $host_config));
        $context->setShell($docker_config->shell());
        $docker_config->shell()->setOutput($context->getOutput());

        $method->runScript($host_config, $context);

        /** @var CommandResult $cr */
        $cr = $context->getResult('commandResult', false);
        if ($cr && $cr->failed()) {
            $cr->throwException(sprintf('Docker task `%s` failed!', $task));
        }
    }

    public function getInternalTasks(): array
    {
        return [
            'waitForServices',
            'copySSHKeys',
            'startRemoteAccess',
            'scaffoldDockerFiles',
        ];
    }

    /**
     * @throws ValidationFailedException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     */
    public function waitForServices(HostConfig $hostconfig, TaskContextInterface $context)
    {
        if (false === $hostconfig['executables']['supervisorctl']) {
            return;
        }
        $max_tries = 10;
        $tries = 0;
        $docker_config = $this->getDockerConfig($hostconfig, $context);
        $container_name = $this->getDockerContainerName($hostconfig, $context);
        $shell = $docker_config->shell();

        if (!$this->isContainerRunning($docker_config, $container_name)) {
            throw new \RuntimeException(sprintf('Docker container %s is not running or could not be discovered! Check your docker config!', $container_name));
        }

        while ($tries < $max_tries) {
            $error_log_level = new ScopedErrorLogLevel($shell, LogLevel::NOTICE);
            $result = $shell->run(sprintf('#!docker exec %s #!supervisorctl status', $container_name), RunOptions::CAPTURE_AND_HIDE_OUTPUT, false);
            $error_log_level = null;

            $count_running = 0;
            $count_services = 0;
            foreach ($result->getOutput() as $line) {
                if ('' != trim($line)) {
                    ++$count_services;
                    if (strpos($line, 'RUNNING')) {
                        ++$count_running;
                    }
                }
            }
            if (0 !== $result->getExitCode()) {
                $this->logger->notice('Error running supervisorctl, check the logs');
            }
            if (0 == $result->getExitCode() && ($count_running == $count_services)) {
                $context->io()->comment('Services up and running!');

                return;
            }
            ++$tries;
            $this->logger->notice(sprintf(
                'Waiting for 5 secs and try again (%d/%d)...',
                $tries,
                $max_tries
            ));
            sleep(5);
        }
        $this->logger->error('Supervisord not coming up at all!');
    }

    public static function getProjectFolder(DockerConfig $docker_config, HostConfig $host_config)
    {
        return $docker_config['rootFolder'].'/'.$host_config['docker']['projectFolder'];
    }

    public function getHostEnvironment(
        HostConfig $host_config,
        TaskContextInterface $context,
        DockerConfig $docker_config,
    ): array {
        $variables = Utilities::buildVariablesFrom($host_config, $context);
        $replacements = Utilities::expandVariables($variables);
        $environment = Utilities::expandStrings($docker_config->get('environment', []), $replacements);

        return $context->getConfigurationService()
            ->getPasswordManager()
            ->resolveSecrets($environment);
    }

    /**
     * @throws ValidationFailedException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException|\Phabalicious\Exception\FabfileNotReadableException
     */
    private function copySSHKeys(HostConfig $hostconfig, TaskContextInterface $context)
    {
        $files = [];
        $temp_files = [];
        $temp_nam_prefix = 'phab-'.md5($hostconfig->getConfigName().mt_rand());

        // Backwards-compatibility:
        if ($file = $context->getConfigurationService()->getSetting('dockerAuthorizedKeyFile')) {
            $files['/root/.ssh/authorized_keys'] = [
                'source' => $file,
                'permissions' => '600',
            ];
        }
        if ($file = $context->getConfigurationService()->getSetting('dockerAuthorizedKeysFile')) {
            $files['/root/.ssh/authorized_keys'] = [
                'source' => $file,
                'permissions' => '600',
            ];
        }
        if ($file = $context->getConfigurationService()->getSetting('dockerKeyFile')) {
            $files['/root/.ssh/id_rsa'] = [
                'source' => $file,
                'permissions' => '600',
            ];
            $files['/root/.ssh/id_rsa.pub'] = [
                'source' => $file.'.pub',
                'permissions' => '644',
            ];
        }

        if ($file = $context->getConfigurationService()->getSetting('dockerKnownHostsFile')) {
            $files['/root/.ssh/known_hosts'] = [
                'source' => $file,
                'permissions' => '600',
            ];
        }

        if ($file = $context->getConfigurationService()->getSetting('dockerNetRcFile')) {
            $files['/root/.netrc'] = [
                'source' => $file,
                'permissions' => '600',
                'optional' => true,
            ];
        }

        $docker_config = $this->getDockerConfig($hostconfig, $context);
        $root_folder = $this->getProjectFolder($docker_config, $hostconfig);

        $shell = $docker_config->shell();

        // If no authorized_keys file is set, then add all public keys from the agent into the container.
        if (empty($files['/root/.ssh/authorized_keys'])) {
            $file = tempnam('/tmp', $temp_nam_prefix);

            try {
                $result = $shell->run(sprintf('#!ssh-add -L > %s', $file));

                $files['/root/.ssh/authorized_keys'] = [
                    'source' => $file,
                    'permissions' => '600',
                ];
                $temp_files[] = $file;
            } catch (FailedShellCommandException $e) {
                $context->io()->warning(sprintf(
                    'Could not add public key to authorized_keys-file: %s',
                    $e->getMessage()
                ));
            }
        }

        if (count($files) > 0) {
            $container_name = $this->getDockerContainerName($hostconfig, $context);
            if (!$this->isContainerRunning($docker_config, $container_name)) {
                throw new \RuntimeException(sprintf('Docker container %s not running, check your `host.docker.name` configuration!', $container_name));
            }
            $shell->run(sprintf('#!docker exec %s mkdir -p /root/.ssh', $container_name));

            foreach ($files as $dest => $data) {
                if (('http://' == substr($data['source'], 0, 7))
                    || ('https://' == substr($data['source'], 0, 8))) {
                    $content = $context->getConfigurationService()->readHttpResource($data['source']);
                    $temp_file = tempnam('/tmp', $temp_nam_prefix);
                    file_put_contents($temp_file, $content);
                    $data['source'] = $temp_file;
                    $temp_files[] = $temp_file;
                } elseif ('/' !== $data['source'][0]) {
                    $data['source'] =
                          $context->getConfigurationService()->getFabfilePath().
                          '/'.
                          $data['source'];
                }

                // Check if file exists
                if (!file_exists($data['source'])) {
                    if (empty($data['optional'])) {
                        throw new \RuntimeException(sprintf('File `%s does not exist, could not copy into container!', $data['source']));
                    } else {
                        $context->io()->comment(sprintf('File `%s does not exist, skipping!', $data['source']));
                        continue;
                    }
                }

                $temp_file = $docker_config['tmpFolder'].'/'.$temp_nam_prefix.'-'.basename($data['source']);
                $shell->putFile($data['source'], $temp_file, $context);

                $shell->run(sprintf('#!docker cp %s %s:%s', $temp_file, $container_name, $dest));
                $shell->run(sprintf('#!docker exec %s #!chmod %s %s', $container_name, $data['permissions'], $dest));
                $shell->run(sprintf('rm %s', $temp_file));
                $context->io()->comment(sprintf('Handled %s successfully!', $dest));
            }
            $shell->run(sprintf('#!docker exec %s #!chmod 700 /root/.ssh', $container_name));
            $shell->run(sprintf('#!docker exec %s #!chown -R root /root/.ssh', $container_name));
        }

        foreach ($temp_files as $temp_file) {
            @unlink($temp_file);
        }
    }

    public function isContainerRunning(HostConfig $docker_config, $container_name): bool
    {
        $this->logger->debug(sprintf('Checking if container %s is running...', $container_name));
        $shell = $docker_config->shell();
        $scoped_loglevel = new ScopedLogLevel($shell, LogLevel::DEBUG);
        $timeout = time() + 2;
        $is_running = false;
        while ($timeout > time() && !$is_running) {
            $result = $shell->run(sprintf(
                '#!docker inspect -f {{.State.Running}} %s',
                $container_name
            ), RunOptions::CAPTURE_AND_HIDE_OUTPUT);

            $output = $result->getOutput();
            $last_line = array_pop($output);
            if ('true' === strtolower(trim($last_line))) {
                $is_running = true;
            }
            if (!$is_running) {
                usleep(10 * 1000); // sleep for 0.1s
            }
        }

        return $is_running;
    }

    /**
     * @throws ValidationFailedException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     */
    public function getIpAddress(HostConfig $host_config, TaskContextInterface $context): bool|string
    {
        if (!empty($this->cache[$host_config->getConfigName()])) {
            return $this->cache[$host_config->getConfigName()];
        }
        $docker_config = $this->getDockerConfig($host_config, $context);
        $shell = $docker_config->shell();
        $scoped_loglevel = new ScopedLogLevel($shell, LogLevel::DEBUG);
        try {
            $container_name = $this->getDockerContainerName($host_config, $context);
        } catch (\RuntimeException $e) {
            return false;
        }

        if (!$this->isContainerRunning($docker_config, $container_name)) {
            return false;
        }

        $result = $shell->run(sprintf(
            '#!docker inspect --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}|{{end}}" %s',
            $container_name
        ), RunOptions::CAPTURE_AND_HIDE_OUTPUT);

        if (0 === $result->getExitCode()) {
            $ips = explode('|', $result->getOutput()[0]);
            $ips = array_filter($ips);
            $ip = reset($ips);
            $this->cache[$host_config->getConfigName()] = $ip;

            return $ip;
        }

        return false;
    }

    /**
     * @throws ValidationFailedException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     */
    public function startRemoteAccess(HostConfig $host_config, TaskContextInterface $context)
    {
        $docker_config = $this->getDockerConfig($host_config, $context);
        $this->getIp($host_config, $context);
        if (is_a($docker_config->shell(), 'SshShellProvider')) {
            $context->setResult('config', $docker_config);
        }
    }

    /**
     * @throws ValidationFailedException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     */
    public function getIp(HostConfig $host_config, TaskContextInterface $context)
    {
        $context->setResult('ip', $this->getIpAddress($host_config, $context));
    }

    /**
     * @throws ValidationFailedException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     */
    public function appCheckExisting(HostConfig $host_config, TaskContextInterface $context)
    {
        // Set outer-shell to the one provided by the docker-configuration.
        $docker_config = $this->getDockerConfig($host_config, $context);
        $context->setResult('outerShell', $docker_config->shell());
        $context->setResult('installDir', $this->getProjectFolder($docker_config, $host_config));
    }

    /**
     * @throws FailedShellCommandException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }

        if (('installCode' === $current_stage) && !$context->getResult('projectCreated', false)) {
            /** @var \Phabalicious\ShellProvider\ShellProviderInterface $shell */
            $shell = $context->get('outerShell', $host_config->shell());
            $install_dir = $context->get('installDir', false);

            if ($install_dir) {
                $shell->pushWorkingDir(dirname($install_dir));
                $shell->run(sprintf('mkdir -p %s', $install_dir));

                $shell->cd($install_dir);
                $shell->run('touch .projectCreated');
                $shell->popWorkingDir();
                $context->setResult('projectCreated', true);
            }
        }

        $this->runAppSpecificTask($host_config, $context);
    }

    /**
     * @throws FailedShellCommandException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    public function appDestroy(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runAppSpecificTask($host_config, $context);
    }

    /**
     * @throws FailedShellCommandException
     * @throws MethodNotFoundException
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws ValidationFailedException
     */
    public function runAppSpecificTask(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }

        $docker_config = $this->getDockerConfig($host_config, $context);
        $shell = $docker_config->shell();

        if (isset($docker_config['tasks'][$current_stage])
            || in_array($current_stage, ['spinUp', 'spinDown', 'deleteContainer'])
        ) {
            $this->runTaskImpl($host_config, $context, $current_stage, false);
        }
    }

    /**
     * @throws MismatchedVersionException
     * @throws MissingDockerHostConfigException
     * @throws ValidationFailedException
     * @throws \RuntimeException
     */
    public function getDockerContainerName(HostConfig $host_config, TaskContextInterface $context): string
    {
        if (!empty($host_config['docker']['name'])) {
            return $host_config['docker']['name'];
        }
        if ($composer_service = $host_config['docker']['service']) {
            $docker_config = $this->getDockerConfig($host_config, $context);
            $shell = $docker_config->shell();
            $cwd = $shell->getWorkingDir();
            $shell->cd(self::getProjectFolder($docker_config, $host_config));
            $result = $shell->run(sprintf('#!docker-compose ps -q %s', $composer_service), RunOptions::CAPTURE_AND_HIDE_OUTPUT);
            $shell->cd($cwd);
            $docker_name = false;
            if ($result->succeeded()) {
                $docker_name = $result->getOutput()[0] ?? false;
            }
            if ($docker_name) {
                $host_config->setChild('docker', 'nameAutoDiscovered', true);
                $host_config->setChild('docker', 'name', $docker_name);

                return $docker_name;
            }

            throw new \RuntimeException(sprintf('Could not get the name of the docker container running the service `%s`', $composer_service));
        }

        return '';
    }

    public function preflightTask(string $task, HostConfig $host_config, TaskContextInterface $context): void
    {
        parent::preflightTask($task, $host_config, $context);

        $needs_running_container = $context->getConfigurationService()->isRunningAppRequired(
            $host_config,
            $context,
            $task
        );

        if ($host_config['docker']['scaffold'] && 'docker' === $task) {
            $this->scaffoldDockerFiles($host_config, $context);
        }

        if ($needs_running_container && empty($host_config['docker']['name'])) {
            $this->logger->info('Try to get docker container name ...');
            try {
                $config = $host_config['docker'];
                $host_config->setChild('docker', 'nameAutoDiscovered', true);
                $host_config->setChild(
                    'docker',
                    'name',
                    $this->getDockerContainerName($host_config, $context)
                );
            } catch (\Exception $e) {
            }
        }
        if ($needs_running_container) {
            $ip = $this->getIpAddress($host_config, $context);
            if (!$ip) {
                throw new \RuntimeException('Container is not available, please check your app-runtime!');
            }
        }
    }

    public function postflightTask(string $task, HostConfig $host_config, TaskContextInterface $context): void
    {
        parent::postflightTask($task, $host_config, $context);

        $needs_running_container = $context->getConfigurationService()->isRunningAppRequired(
            $host_config,
            $context,
            $task
        );
        // Reset any cached docker container name after a docker task.
        if (!$needs_running_container && !empty($host_config['docker']['nameAutoDiscovered'])) {
            $host_config->setChild('docker', 'name', null);
            $host_config->setChild('docker', 'nameAutoDiscovered', null);
        }
    }

    /**
     * @throws MismatchedVersionException
     * @throws ValidationFailedException
     * @throws MissingDockerHostConfigException
     */
    public function dockerCompose(HostConfig $host_config, TaskContextInterface $context)
    {
        $docker_config = $this->getDockerConfig($host_config, $context);
        $shell = $docker_config->shell();

        $arguments = $context->get('command', false);
        if (!$arguments) {
            throw new \InvalidArgumentException('Missing command arguments for dockerCompose');
        }

        $environment = $this->getHostEnvironment($host_config, $context, $docker_config);

        $context->setResult('shell', $shell);

        $command_parts = [
            sprintf('cd %s', self::getProjectFolder($docker_config, $host_config)),
        ];
        foreach ($environment as $k => $v) {
            $command_parts[] = sprintf(' export %s=%s', $k, escapeshellarg($v));
        }
        $command_parts[] = sprintf('#!docker-compose %s', $arguments);

        $command = implode('&&', $command_parts);
        $command = $shell->expandCommand($command);
        $context->setResult('command', [
            $command,
        ]);
    }

    protected function scaffoldDockerFiles(HostConfig $host_config, TaskContextInterface $context): void
    {
        static $scaffolder_did_run = [];
        if (!empty($scaffolder_did_run[$host_config->getConfigName()])) {
            return;
        }
        $scaffolder_did_run[$host_config->getConfigName()] = true;

        $docker_config = $this->getDockerConfig($host_config, $context);
        $project_folder = self::getProjectFolder($docker_config, $host_config);
        $shell = $docker_config->shell();

        $this->runScaffolder($host_config, $context, $shell, $project_folder, 'docker');
    }
}
