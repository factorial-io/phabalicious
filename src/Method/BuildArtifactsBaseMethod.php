<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\MissingScriptCallbackImplementation;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\ShellProviderInterface;
use Phabalicious\Utilities\AppDefaultStages;

abstract class BuildArtifactsBaseMethod extends BaseMethod
{

    /**
     * Build the artifact into given directory.
     *
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param ShellProviderInterface $shell
     * @param string $install_dir
     * @param array $stages
     * @throws MethodNotFoundException
     * @throws MissingScriptCallbackImplementation
     * @throws TaskNotFoundInMethodException
     */
    protected function buildArtifact(
        HostConfig $host_config,
        TaskContextInterface $context,
        ShellProviderInterface $shell,
        string $install_dir,
        array $stages
    ) {
        $cloned_host_config = clone $host_config;
        $keys = ['rootFolder', 'composerRootFolder', 'gitRootFolder'];
        foreach ($keys as $key) {
            $cloned_host_config[$key] = $install_dir;
        }
        $shell->cd($cloned_host_config['tmpFolder']);
        $context->set('outerShell', $shell);

        AppDefaultStages::executeStages(
            $context->getConfigurationService()->getMethodFactory(),
            $cloned_host_config,
            $stages,
            'appCreate',
            $context,
            'Creating code'
        );

        // Run deploy scripts
        /** @var ScriptMethod $script_method */
        $script_method = $context->getConfigurationService()->getMethodFactory()->getMethod('script');
        $context->set('variables', [
            'installFolder' => $install_dir
        ]);
        $context->set('rootFolder', $install_dir);
        $script_method->runTaskSpecificScripts($cloned_host_config, 'deploy', $context);

        $context->setResult('skipResetStep', true);
    }
}
