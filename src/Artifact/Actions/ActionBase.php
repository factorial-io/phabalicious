<?php

namespace Phabalicious\Artifact\Actions;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\ArtifactsBaseMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

abstract class ActionBase implements ActionInterface
{
    protected $arguments= [];

    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
    }

    protected function getArguments() : array
    {
        return $this->arguments;
    }

    protected function getArgument($name, $default = null)
    {
        return $this->arguments[$name] ?? $default;
    }

    public function validateConfig($host_config, array $action_config, ValidationErrorBagInterface $errors)
    {
        $service = new ValidationService(
            $action_config,
            $errors,
            sprintf('host-config.%s.%s', $host_config['configName'], ArtifactsBaseMethod::ACTIONS_KEY)
        );
        $service->hasKeys([
            'action' => 'Every action needs the type of action to perform',
            'arguments' => 'Missing arguments for an action'
        ]);
        if (isset($action_config['arguments'])) {
            $service->isArray('arguments', 'arguments need to be an array!');
        }

        if (!empty($action_config['action']) && is_array($action_config['arguments'])) {
            $service = new ValidationService(
                $action_config['arguments'],
                $errors,
                sprintf('%s.arguments', $action_config['action'])
            );
            $this->validateArgumentsConfig($action_config, $service);
        }
    }

    abstract protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation);


    public function run(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('outerShell', $host_config->shell());
        $install_dir = $context->get('installDir', false);
        $target_dir = $context->get('targetDir', false);

        $shell->pushWorkingDir($install_dir);
        $this->runImplementation($host_config, $context, $shell, $install_dir, $target_dir);
        $shell->popWorkingDir();
    }

    protected function runImplementation(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $install_dir,
        string $target_dir
    ) {
        throw new \RuntimeException('Missing run implementation');
    }
}
