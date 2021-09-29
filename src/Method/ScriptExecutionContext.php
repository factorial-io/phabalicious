<?php

namespace Phabalicious\Method;

use http\Exception\RuntimeException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\ShellProvider\ShellProviderInterface;
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

    protected $shell;

    protected $initialWorkingDir;


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
        switch ($this->currentContextName) {
            case self::DOCKER_COMPOSE_RUN:
                $shell->cd($this->getArgument('rootFolder'));
                $shell->run('docker-compose pull && docker-compose build');
                $this->shell = $shell->startSubShell([
                    'docker-compose',
                    'run',
                    $this->getArgument('service'),
                    $this->getArgument('shellExecutable', '/bin/sh')
                ]);
                $this->setInitialWorkingDir('/app');

                break;

            case self::DOCKER_IMAGE:
                $shell->run(sprintf('docker pull %s', $this->getArgument('image')));
                $this->shell = $shell->startSubShell([
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
                    $this->getArgument('image'),
                    $this->getArgument('shellExecutable', '/bin/sh')
                ]);
                $this->setInitialWorkingDir('/app');

                break;

            case self::KUBE_CTL:
                $this->shell = $this->getArgument('kubectlShell');
                $this->setInitialWorkingDir($this->getArgument('rootFolder'));
                break;
            default:
                $this->shell = $shell;
                $this->setInitialWorkingDir($this->workingDir);
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
            $this->shell->cd($this->workingDir);
            $this->shell->cd($this->getArgument('rootFolder'));
            $this->shell->run('docker-compose rm -s -v --force');
        }
    }

    public function getShell(): ShellProviderInterface
    {
        return $this->shell;
    }

    public function getInitialWorkingDir()
    {
        return $this->initialWorkingDir;
    }

    protected function setInitialWorkingDir($working_dir)
    {
        $this->initialWorkingDir = $working_dir;
    }
}
