<?php

namespace Phabalicious\Method;

use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\ShellProvider\SubShellProvider;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class ScriptExecutionContext
{
    const DOCKER_IMAGE = 'docker-image';
    const DOCKER_COMPOSE_RUN = 'docker-compose-run';
    const HOST = 'host';
    const KUBE_CTL = 'kubectl';

    const VALID_CONTEXTS = [
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

        switch ($this->currentContextName) {
            case self::DOCKER_COMPOSE_RUN:
                $this->dockerComposeRootDir = $this->getArgument('rootFolder');
                $shell->applyEnvironment($this->getArgument('environment', []));
                $shell->cd($this->dockerComposeRootDir);
                $shell->run(
                    'docker-compose pull && docker-compose build',
                    false,
                    true
                );
                $this->shell = $shell->startSubShell([
                    'docker-compose',
                    'run',
                    $this->getArgument('service'),
                    $this->getArgument('shellExecutable', '/bin/sh')
                ]);
                $this->setScriptWorkingDir($this->getArgument('workingDir', '/app'));

                break;

            case self::DOCKER_IMAGE:
                $shell->applyEnvironment($this->getArgument('environment', []));
                $shell->run(sprintf('docker pull %s', $this->getArgument('image')));
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
                    '-v',
                    sprintf('%s:/app', $this->workingDir),
                ];
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

    public function exit()
    {
        if ($this->currentContextName != self::HOST) {
            $this->shell->terminate();
        }

        if ($this->currentContextName == self::DOCKER_COMPOSE_RUN) {
            $this->shell->cd($this->initialWorkingDir);
            $this->shell->cd($this->dockerComposeRootDir);
            $this->shell->run('docker-compose rm -s -v --force');
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
}
