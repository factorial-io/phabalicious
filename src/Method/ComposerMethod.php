<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;

class ComposerMethod extends RunCommandBaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'composer';
    }

    protected function getExecutableName(): string
    {
        return 'composer';
    }

    protected function getRootFolderKey(): string
    {
        return 'composerRootFolder';
    }

    protected function prepareCommand(HostConfig $host_config, TaskContextInterface $context, string $command)
    {
        if (!in_array($host_config['type'], array('dev', 'test'))) {
            $command .= ' --no-dev --optimize-autoloader';
        }
        return $command;
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

    public function composer(HostConfig $host_config, TaskContextInterface $context)
    {
        $command = $context->get('command');
        $this->runCommand($host_config, $context, $command);
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
