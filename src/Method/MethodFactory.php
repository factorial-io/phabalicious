<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Exception\MethodNotFoundException;
use Psr\Log\LoggerInterface;

class MethodFactory {

    /**
     * @var MethodInterface[]
     */
    protected $methods = [];

    /**
     * @var \Phabalicious\Configuration\ConfigurationService
     */
    protected $configuration;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected $lookupCache = [];

    public function __construct(ConfigurationService $configuration, LoggerInterface $logger)
    {
        $this->configuration = $configuration;
        $configuration->setMethodFactory($this);

        $this->logger = $logger;
    }

    public function addMethod(MethodInterface $method)
    {
        $this->methods[$method->getName()] = $method;
    }

    public function getMethod($name): MethodInterface
    {
        if (isset($this->lookupCache[$name])) {
            return $this->lookupCache[$name];
        }

        foreach ($this->methods as $method) {
            if ($method->supports($name)) {
                $this->lookupCache[$name] = $method;
                return $method;
            }
        }

        throw new MethodNotFoundException('Could not find implementation for method ' . $name);
    }

    public function runTask(
        string $task_name,
        array $configuration,
        TaskContextInterface $context = null,
        $nextTasks = []
    ) {
        if (!$context) {
            $context = new TaskContext();
        }
        $this->preflight('preflight', $task_name, $configuration, $context);
        $this->runTaskImpl($task_name . 'Prepare', $configuration, $context, false);
        $this->runTaskImpl($task_name, $configuration, $context, true);
        if (!empty($nextTasks)) {
            foreach ($nextTasks as $next_task_name) {
                $this->runTask($next_task_name, $configuration, $context);
            }
        }
        $this->runTaskImpl($task_name . 'Finished', $configuration, $context, false);
        $this->preflight('postflight', $task_name, $configuration, $context);

        return $context;
    }

    /**
     * Run a task.
     *
     * @param $task_name
     * @param $configuration
     * @param \Phabalicious\Method\TaskContextInterface $context
     * @param $fallback_allowed
     *
     * @throws \Phabalicious\Exception\MethodNotFoundException
     */
    protected function runTaskImpl($task_name, $configuration, TaskContextInterface $context, $fallback_allowed)
    {
        $fn_called = false;

        if (!$context->get('quiet')) {
            $this->logger->debug('Running task ' . $task_name . ' on configuration ' . $configuration['config_name']);
        }

        foreach ($configuration['needs'] as $method_name) {
            $method = $this->getMethod($method_name);
            if (method_exists($method, $task_name)) {
                $fn_called = true;
                $this->callImpl($method, $task_name, $configuration, $context);
            }
        }

        if (!$fn_called && $fallback_allowed) {
            foreach ($configuration['needs'] as $method_name) {
                $this->getMethod($method_name)->fallback($task_name, $configuration, $context);
            }
        }
    }

    private function callImpl(
        MethodInterface $method,
        string $task_name,
        array $configuration,
        TaskContextInterface $context
    ) {
        $overrides = [];
        foreach ($configuration['needs'] as $method_name) {
            if ($overridden_name = $this->getMethod($method_name)->getOverriddenMethod()) {
                $overrides[$method_name] = $overridden_name;
            }
        }
        $method_name = $method->getName();
        if (isset($overrides[$method_name])) {
            $this->logger->info('Use override ' . $overrides[$method_name] . ' for ' . $method_name);
            $method = $this->getMethod($overrides[$method_name]);
        }

        $method->{$task_name}($configuration, $context);
    }

    /**
     * Run preflight/ postflight-step
     *
     * @param string $step_name
     * @param string $task_name
     * @param array $configuration
     * @param \Phabalicious\Method\TaskContextInterface $context
     *
     * @throws \Phabalicious\Exception\MethodNotFoundException
     */
    private function preflight(
        string $step_name,
        string $task_name,
        array $configuration,
        TaskContextInterface $context
    ) {
        foreach ($configuration['needs'] as $method_name) {
            $method = $this->getMethod($method_name);
            $method->{$step_name . "Task"}($task_name, $configuration, $context);
        }
    }

    public function all()
    {
        return $this->methods;
    }

    public function getSubset(array $needs)
    {
        return array_map(function ($elem) {
            return $this->getMethod($elem);
        }, $needs);
    }
}