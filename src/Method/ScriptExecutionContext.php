<?php

namespace Phabalicious\Method;

use http\Exception\RuntimeException;
use Phabalicious\ShellProvider\ShellProviderInterface;

class ScriptExecutionContext
{
    const DOCKER_IMAGE = 'docker-image';
    const HOST = 'host';
    const KUBE_CTL = 'kubectl';

    const VALID_CONTEXTS = [
        self::DOCKER_IMAGE,
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
        if (!self::validate($context_name)) {
            throw new \RuntimeException(sprintf('Unknown script context name `%s`', $context_name));
        }
        $this->workingDir = $working_dir;
        $this->currentContextName = $context_name;
        $this->contextData = $context_data;
    }

    public static function validate($context_name): bool
    {
        return in_array($context_name, self::VALID_CONTEXTS);
    }

    protected function getArgument($name, $default = null)
    {
        return $this->contextData[$name] ?? $default;
    }

    public function enter(ShellProviderInterface $shell): ShellProviderInterface
    {
        switch ($this->currentContextName) {
            case self::DOCKER_IMAGE:
                $this->checkArguments(['image']);
                $this->shell = $shell->startSubShell([
                    'docker',
                    'run',
                    '-e',
                    'PHAB_SUB_SHELL=1',
                    '-i',
                    '-w',
                    '/app',
                    '-u',
                    sprintf('%d:%d', posix_getuid(), posix_getgid()),
                    '-v',
                    sprintf('%s:/app', $this->workingDir),
                    $this->getArgument('image'),
                    $this->getArgument('shellExecutable', '/bin/sh')
                ]);
                $this->setInitialWorkingDir('/app');

                break;

            case self::KUBE_CTL:
                $this->checkArguments(['kubectlShell', 'rootFolder']);
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
        $this->shell->terminate();
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

    protected function checkArguments(array $array)
    {
        foreach ($array as $arg) {
            if (is_null($this->getArgument($arg))) {
                throw new \RuntimeException(sprintf(
                    'Cant run script in contect %s, as %s not provided!',
                    $this->currentContextName,
                    $arg
                ));
            }
        }
    }
}
