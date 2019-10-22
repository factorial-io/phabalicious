<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class NpmMethod extends RunCommandBaseMethod
{

    public function getName(): string
    {
        return 'npm';
    }

    protected function getExecutableName(): string
    {
        // TODO: Implement getExecutableName() method.
    }

    protected function getRootFolderKey(): string
    {
        // TODO: Implement getRootFolderKey() method.
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     */
    public function resetPrepare(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = 'install ';
        $this->runCommand($host_config, $context, $command);
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

        if ($current_stage == 'installDependencies') {
            $this->resetPrepare($host_config, $context);
        }
    }

    public function appUpdate(HostConfig $host_config, TaskContextInterface $context)
    {
        $this->runCommand($host_config, $context, 'update');
    }

}
