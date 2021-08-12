<?php


namespace Phabalicious\Artifact\Actions\Base;

use Phabalicious\Artifact\Actions\ActionBase;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\ScriptExecutionContext;
use Phabalicious\Method\ScriptMethod;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\ShellProvider\CommandResult;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationService;

class ScriptAction extends ActionBase
{
    private $runInTargetDir = true;

    public function __construct($runInTargetDir = true)
    {
        $this->runInTargetDir = $runInTargetDir;
    }

    protected function validateArgumentsConfig(array $action_arguments, ValidationService $validation)
    {
        if (Utilities::isAssocArray($action_arguments['arguments'])) {
            $validation->hasAtLeast(['script', 'name'], 'missing key');

            if (isset($action_arguments['arguments']['context'])) {
                $validation_errors = ScriptExecutionContext::validate($action_arguments['arguments']);
                $validation->getErrorBag()->addErrorBag($validation_errors);
            }
        }
    }

    protected function runImplementation(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $install_dir,
        string $target_dir
    ) {
        $dir = $this->runInTargetDir ? $target_dir : $install_dir;
        $shell->pushWorkingDir($dir);

        $context->set(ScriptMethod::SCRIPT_CONTEXT, $this->getArgument('context', 'host'));
        $context->set(ScriptMethod::SCRIPT_CONTEXT_DATA, $this->getArguments());

        /** @var ScriptMethod $script */
        $script = $context->getConfigurationService()->getMethodFactory()->getMethod('script');

        if (!$script_data = $this->getArgument('script')) {
            if (!$script_name  = $this->getArgument('name')) {
                $script_data = $this->getArguments();
            } else {
                $script_data = $context->getConfigurationService()->findScript($host_config, $script_name);
                if (!$script_data) {
                    throw new \InvalidArgumentException(sprintf("Could not find named script `%s`", $script_name));
                }
            }
        }

        $cloned_context = clone $context;
        $cloned_context->set('rootFolder', $dir);
        $cloned_context->set(ScriptMethod::SCRIPT_DATA, $script_data);
        $cloned_context->set('variables', $context->get('deployArguments'));

        $saved = $script->getBreakOnFirstError();
        $script->setBreakOnFirstError(true);

        $script->runScript($host_config, $cloned_context);
        /** @var CommandResult $cr */
        if ($cr = $context->getResult('commandResult')) {
            if ($cr->failed()) {
                $cr->throwException('Script action failed with an error!');
            }
        }

        $script->setBreakOnFirstError($saved);
        $context->mergeResults($cloned_context);

        $shell->popWorkingDir();
    }
}
