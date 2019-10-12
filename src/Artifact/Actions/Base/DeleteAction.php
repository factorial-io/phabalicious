<?php

namespace Phabalicious\Artifact\Actions\Base;


use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationService;

class DeleteAction extends ActionBase
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

        $files_to_delete = $this->getArguments();
        foreach ($files_to_delete as $file) {
            $full_path = $target_dir . '/' . $file;
            $shell->run(sprintf('rm -rf %s', $full_path));
        }

        $shell->popWorkingDir();
    }
}
