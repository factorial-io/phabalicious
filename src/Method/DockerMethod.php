<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\DockerConfig;
use Phabalicious\Configuration\HostConfig;

class DockerMethod extends BaseMethod implements MethodInterface
{

    public function getName(): string
    {
        return 'docker';
    }

    public function supports(string $method_name): bool
    {
        return $method_name === 'docker';
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    public function docker(HostConfig $host_config, TaskContextInterface $context)
    {
        $docker_config = $context->get('docker_config');
        $task = $context->get('docker_task');

        $tasks = $docker_config['tasks'];

        $this->runTaskImpl($host_config, $context, $task . 'Prepare');
        $this->runTaskImpl($host_config, $context, $task);
        $this->runTaskImpl($host_config, $context, $task . 'Finished');
    }

    /**
     * @param HostConfig $host_config
     * @param TaskContextInterface $context
     * @param $task
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MissingScriptCallbackImplementation
     */
    private function runTaskImpl(HostConfig $host_config, TaskContextInterface $context, $task)
    {
        $this->logger->info('Running docker-task `' . $task . '` on `' . $host_config['configName']);

        if (method_exists($this, $task)) {
            $this->{$task}($host_config, $context);
            return;
        }

        /** @var DockerConfig $docker_config */
        $docker_config = $context->get('docker_config');
        $tasks = $docker_config['tasks'];

        if (empty($tasks[$task])) {
            return;
        }

        $script = $tasks[$task];
        $environment = $docker_config->get('environment', []);
        $callbacks = [];

        /** @var ScriptMethod $method */
        $method = $context->getConfigurationService()->getMethodFactory()->getMethod('script');
        $context->set('scriptData', $script);
        $context->set('variables', [
            'dockerHost' => $docker_config->raw(),
        ]);
        $context->set('environment', $environment);
        $context->set('callbacks', $callbacks);
        $context->set('rootFolder', $docker_config['rootFolder']);
        $context->setShell($docker_config->shell());
        $docker_config->shell()->setOutput($context->getOutput());

        $method->runScript($host_config, $context);
    }

}