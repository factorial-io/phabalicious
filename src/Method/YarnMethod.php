<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;

class YarnMethod extends RunCommandBaseMethod
{

    public function getName(): string
    {
        return 'yarn';
    }

    protected function getExecutableName(): string
    {
        return 'yarn';
    }

    protected function getRootFolderKey(): string
    {
        return 'yarnRootFolder';
    }

    protected function prepareCommand(HostConfig $host_config, TaskContextInterface $context, string $command)
    {
        $production = !in_array($host_config['type'], array('dev', 'test'));
        $command .= sprintf(' --production=%s', $production ? 'true' : 'false');
        $command .= ' --no-interaction  --silent';

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

    public function appCreate(HostConfig $host_config, TaskContextInterface $context)
    {
        if (!$current_stage = $context->get('currentStage', false)) {
            throw new \InvalidArgumentException('Missing currentStage on context!');
        }

        if ($current_stage == 'installDependencies') {
            $this->resetPrepare($host_config, $context);
        }
    }
}
