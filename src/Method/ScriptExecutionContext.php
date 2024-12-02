<?php

namespace Phabalicious\Method;

use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\RunOptions;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\ShellProvider\SubShellProvider;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class ScriptExecutionContext
{
    public const DOCKER_IMAGE = 'docker-image';
    public const DOCKER_COMPOSE_RUN = 'docker-compose-run';
    public const HOST = 'host';
    public const KUBE_CTL = 'kubectl';

    public const VALID_CONTEXTS = [
        self::DOCKER_IMAGE,
        self::DOCKER_COMPOSE_RUN,
        self::HOST,
        self::KUBE_CTL
    ];


    protected $workingDir;

    protected $currentContextName;

    protected $contextData;

    /**
     * @var SubShellProvider
     */
    protected $shell;

    protected $initialWorkingDir;

    protected $scriptWorkingDir;

    protected $dockerComposeRootDir;

    protected $uniqueHash;


    public function __construct($working_dir, string $context_name, array $context_data)
    {
        $errors = self::validate(array_merge($context_data, [
            'context' => $context_name
        ]));
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }
        $this->workingDir = $working_dir;
        $this->currentContextName = $context_name;
        $this->contextData = $context_data;
    }

    public static function validate(array $arguments): ValidationErrorBagInterface
    {
        $errors = new ValidationErrorBag();
        $validation = new ValidationService($arguments, $errors, "ScriptExecutionContext");

        $validation->isOneOf('context', self::VALID_CONTEXTS);
        if ($errors->hasErrors()) {
            return $errors;
        }

        switch ($arguments['context']) {
            case self::DOCKER_COMPOSE_RUN:
                $validation->hasKey('rootFolder', 'the folder where the docker-compose is located at.');
                $validation->hasKey('service', 'The service where to execute the script.');
                break;

            case self::DOCKER_IMAGE:
                $validation->hasKey('image', 'The name of the docker image to use');
                break;

            case self::KUBE_CTL:
                $validation->hasKey('kubectlShell', 'the kubectl-shell');
                $validation->hasKey('rootFolder', 'the rootFolder to use');
                break;

            default:
        }

        return $errors;
    }

    protected function getArgument($name, $default = null)
    {
        return $this->contextData[$name] ?? $default;
    }

    public function enter(ShellProviderInterface $shell): ShellProviderInterface
    {
        $this->initialWorkingDir = $shell->getWorkingDir();
        $this->uniqueHash = Utilities::getTempNamePrefixFromString(
            $shell->getHostConfig()->getConfigName(),
            Utilities::slugify(basename($this->initialWorkingDir), '-')
        );

        switch ($this->currentContextName) {
            case self::DOCKER_COMPOSE_RUN:
                $this->dockerComposeRootDir = $this->getArgument('rootFolder');
                $shell->cd($this->dockerComposeRootDir);
                $this->applyEnvironmentToHostShell($shell);
                if ($this->getArgument('pullLatestImage', true)) {
                    $shell->run($this->getDockerComposeCmd('pull', '--quiet'), RunOptions::NONE, true);
                }
                $shell->run($this->getDockerComposeCmd('build', '--quiet'), RunOptions::NONE, true);
                $this->shell = $shell->startSubShell($this->getDockerComposeCmdAsArray(
                    'run',
                    '--rm',
                    $this->getArgument('service'),
                    $this->getArgument('shellExecutable', '/bin/sh')
                ));
                $this->setScriptWorkingDir($this->getArgument('workingDir', '/app'));

                break;

            case self::DOCKER_IMAGE:
                $root_folder =$this->getArgument('rootFolder', $this->workingDir);
                $working_dir = $shell->realPath($root_folder);
                if (!$working_dir) {
                    throw new \RuntimeException(sprintf('Can\'t resolve working dir %s!', $root_folder));
                }
                $this->applyEnvironmentToHostShell($shell);
                if ($this->getArgument('pullLatestImage', true)) {
                    $shell->run(sprintf('docker pull %s', $this->getArgument('image')));
                }
                $cmd = [
                    'docker',
                    'run',
                    '--rm',
                    '-e',
                    'PHAB_SUB_SHELL=1',
                    '-i',
                    '-w',
                    '/app',
                    '-u',
                    $this->getArgument(
                        'user',
                        sprintf('%d:%d', posix_getuid(), posix_getgid())
                    ),
                ];
                if ($this->getArgument('bindCurrentFolder', true)) {
                    $cmd[] = '-v';
                    $cmd[] = sprintf('%s:/app', $working_dir);
                }
                if (!is_null($entrypoint = $this->getArgument('entryPoint'))) {
                    $cmd[] = sprintf('--entrypoint="%s"', $entrypoint);
                }

                $cmd[] = $this->getArgument('image');
                $cmd[] = $this->getArgument('shellExecutable', '/bin/sh');

                $this->shell = $shell->startSubShell($cmd);
                $this->setScriptWorkingDir('/app');

                break;

            case self::KUBE_CTL:
                $this->shell = $this->getArgument('kubectlShell');
                $this->setScriptWorkingDir($this->getArgument('rootFolder'));
                break;

            default:
                $this->shell = $shell;
                $this->setScriptWorkingDir($this->workingDir);
                break;
        }

        return $this->getShell();
    }

    public function exit(): void
    {
        if ($this->currentContextName !== self::HOST) {
            $this->shell->terminate();
        }

        if ($this->currentContextName === self::DOCKER_COMPOSE_RUN) {
            $this->shell->cd($this->initialWorkingDir);
            $this->shell->cd($this->dockerComposeRootDir);

            $this->applyEnvironmentToHostShell($this->shell);

            $this->shell->run($this->getDockerComposeCmd('down', '-v --rmi=local'));
        }
    }

    public function getShell(): ShellProviderInterface
    {
        return $this->shell;
    }

    public function getScriptWorkingDir()
    {
        return $this->scriptWorkingDir;
    }

    protected function setScriptWorkingDir($working_dir)
    {
        $this->scriptWorkingDir = $working_dir;
    }

    private function getDockerComposeCmdAsArray(string $cmd, ...$args): array
    {
        $return = [
            'docker-compose',
            '-p',
            $this->uniqueHash,
            $cmd
            ];

        return array_merge($return, $args);
    }

    private function getDockerComposeCmd($cmd, ...$args): string
    {
        $result = $this->getDockerComposeCmdAsArray($cmd, ...$args);
        return implode(' ', $result);
    }

    /**
     * @param \Phabalicious\ShellProvider\ShellProviderInterface $shell
     *
     * @return void
     */
    protected function applyEnvironmentToHostShell(ShellProviderInterface $shell): void
    {
        $environment = $this->getArgument('environment', []);
        $environment['USER_ID'] = $this->getArgument(
            'user',
            $shell->run('id -u', RunOptions::CAPTURE_AND_HIDE_OUTPUT, true)->getTrimmedOutput()
        );
        $environment['GROUP_ID'] = $this->getArgument(
            'group',
            $shell->run('id -g', RunOptions::CAPTURE_AND_HIDE_OUTPUT, true)->getTrimmedOutput()
        );
        $shell->applyEnvironment($environment);
    }
}
