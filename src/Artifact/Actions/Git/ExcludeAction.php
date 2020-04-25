<?php


namespace Phabalicious\Artifact\Actions\Git;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationService;

class ExcludeAction extends ActionBase
{

    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
    }

    public function run(HostConfig $host_config, TaskContextInterface $context)
    {
        /** @var ShellProviderInterface $shell */
        $shell = $context->get('outerShell', $host_config->shell());
        $target_dir = $context->get('targetDir', false);

        $shell->pushWorkingDir($target_dir);
        foreach ($this->getArguments() as $argument) {
            $shell->run(sprintf('#!git checkout %s', $argument));
        }
        $shell->popWorkingDir();
    }
}
