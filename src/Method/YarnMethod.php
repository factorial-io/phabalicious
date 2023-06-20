<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class YarnMethod extends RunCommandBaseMethod
{

    public function getName(): string
    {
        return 'yarn';
    }

    public function getDeprecationMapping(): array
    {
        $mapping = parent::getDeprecationMapping();
        $prefix = $this->getConfigPrefix();
        return array_merge($mapping, [
            "{$prefix}BuildCommand" => "{$prefix}.buildCommand",
        ]);
    }

    public function validateConfig(Node $config, ValidationErrorBagInterface $errors)
    {
        parent::validateConfig($config, $errors);
        $service = new ValidationService($config, $errors, 'Yarn');
        $service->deprecate([
            "yarnBuildCommand" => "please change to `yarn.buildCommand`",
        ]);
        $service->hasKey('yarn.buildCommand', 'build command to run with yarn');
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     *
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     * @throws \Phabalicious\Exception\ValidationFailedException
     */
    public function resetPrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runCommand($host_config, $context, 'install');
        $this->runCommand($host_config, $context, $host_config->getProperty('yarn.buildCommand'));
    }

    public function installPrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->resetPrepare($host_config, $context);
    }

    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }

        if ($current_stage === 'installDependencies') {
            $this->resetPrepare($host_config, $context);
        }
    }

    public function yarn(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');
        $this->runCommand($host_config, $context, $command);
    }
}
