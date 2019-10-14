<?php


namespace Phabalicious\Artifact\Actions\Base;


use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Validation\ValidationService;

class ScriptAction extends ActionBase
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

        /** @var ScriptMethod $script */
        $script = $context->getConfigurationService()->getMethodFactory()->getMethod('script');

        $cloned_context = clone $context;
        $cloned_context->set('rootFolder', $target_dir);
        $cloned_context->set('scriptData', $this->getArguments());

        $script->runScript($host_config, $cloned_context);

        $context->mergeResults($cloned_context);

        $shell->popWorkingDir();
    }

}