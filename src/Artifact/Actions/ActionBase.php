<?php

namespace Phabalicious\Artifact\Actions;


use Phabalicious\Method\ArtifactsBaseMethod;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

abstract class ActionBase implements ActionInterface
{
    protected $arguments= [];

    public function setArguments($arguments) {
        $this->arguments = $arguments;
    }

    protected function getArguments() : array
    {
        return $this->arguments;
    }

    protected function getArgument($name) {
        return $this->arguments[$name] ?? null;
    }

    public function validateConfig($host_config, array $action_config, ValidationErrorBagInterface $errors) {
        $service = new ValidationService($action_config, $errors, sprintf(
            'host-config.%s.%s.actions', $host_config['configName'], ArtifactsBaseMethod::PREFS_KEY));
        $service->hasKeys([
            'action' => 'Every action needs the type of action to perform',
            'arguments' => 'Missing arguments for an action'
        ]);
        if (isset($action_config['arguments'])) {
            $service->isArray('arguments', 'arguments need to be an array!');
        }

        if (!empty($action_config['action']) && is_array($action_config['arguments'])) {
            $service = new ValidationService($action_config['arguments'], $errors, sprintf('%s.arguments', $action_config['action']));
            $this->validateArgumentsConfig($action_config, $service);
        }
    }

    abstract protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation);

}
