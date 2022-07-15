<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\MethodNotFoundException;
use Phabalicious\Exception\TaskNotFoundInMethodException;
use Phabalicious\ShellProvider\TunnelHelper\TunnelHelperFactory;
use Psr\Log\LoggerInterface;

class MethodFactory
{

    /**
     * @var MethodInterface[]
     */
    protected $methods = [];

    /**
     * @var ConfigurationService
     */
    protected $configuration;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $tunnelHelperFactory;

    protected $lookupCache = [];

    /**
     * MethodFactory constructor.
     *
     * @param ConfigurationService $configuration
     * @param LoggerInterface $logger
     */
    public function __construct(ConfigurationService $configuration, LoggerInterface $logger)
    {
        $this->configuration = $configuration;
        $this->tunnelHelperFactory = new TunnelHelperFactory($logger);
        $configuration->setMethodFactory($this);

        $this->logger = $logger;
    }

    /**
     * Add a method.
     *
     * @param MethodInterface $method
     */
    public function addMethod(MethodInterface $method)
    {
        $this->methods[$method->getName()] = $method;
        if ($this->tunnelHelperFactory) {
            $method->setTunnelHelperFactory($this->tunnelHelperFactory);
        }
    }

    /**
     * Get a method by name.
     *
     * @param string $name
     *
     * @return MethodInterface
     * @throws MethodNotFoundException
     */
    public function getMethod(string $name): MethodInterface
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

    /**
     * Run a task.
     *
     * @param string $task_name
     * @param HostConfig $configuration
     * @param TaskContextInterface|NULL $context
     * @param array $nextTasks
     *
     * @return TaskContextInterface
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    public function runTask(
        string $task_name,
        HostConfig $configuration,
        TaskContextInterface $context = null,
        array $nextTasks = []
    ) {
        $saved_next_tasks = $context->getResult('runNextTasks', []);
        $context->setResult('runNextTasks', $nextTasks);
        $this->preflight('preflight', $task_name, $configuration, $context);
        $this->runTaskImpl($task_name . 'Prepare', $configuration, $context, false);
        $this->runTaskImpl($task_name, $configuration, $context, true);

        $nextTasks = $context->getResult('runNextTasks', []);
        if (!empty($nextTasks)) {
            foreach ($nextTasks as $next_task_name) {
                $this->runTask($next_task_name, $configuration, $context);
            }
        }
        $this->runTaskImpl($task_name . 'Finished', $configuration, $context, false);
        $this->preflight('postflight', $task_name, $configuration, $context);

        $context->setResult('runNextTasks', $saved_next_tasks);
        return $context;
    }

    /**
     * Run a task (implementation).
     *
     * @param string $task_name
     * @param HostConfig $configuration
     * @param TaskContextInterface $context
     * @param bool $fallback_allowed
     *
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    protected function runTaskImpl(
        string $task_name,
        HostConfig $configuration,
        TaskContextInterface $context,
        $fallback_allowed
    ) {
        $fn_called = false;

        if (!$context->get('quiet')) {
            $this->logger->debug('Running task ' . $task_name . ' on configuration ' . $configuration->getConfigName());
        }

        foreach ($configuration['needs'] as $method_name) {
            $method = $this->getMethod($method_name);
            if (method_exists($method, $task_name)) {
                $fn_called = true;
                $this->callImpl($method, $task_name, $configuration, $context, true);
            }
        }

        if (!$fn_called && $fallback_allowed) {
            foreach ($configuration['needs'] as $method_name) {
                $this->getMethod($method_name)->fallback($task_name, $configuration, $context);
            }
        }
    }

    /**
     * Call a method (implementation).
     *
     * @param MethodInterface $method
     * @param string $task_name
     * @param HostConfig $configuration
     * @param TaskContextInterface $in_context
     * @param bool $optional
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    private function callImpl(
        MethodInterface $method,
        string $task_name,
        HostConfig $configuration,
        TaskContextInterface $in_context,
        bool $optional
    ) {
        $context = clone $in_context;
        $overrides = [];
        foreach ($configuration['needs'] as $method_name) {
            if ($overridden_name = $this->getMethod($method_name)->getOverriddenMethod()) {
                $overrides[$overridden_name] = $method_name;
            }
        }
        $method_name = $method->getName();
        $context->set('currentMethod', $method_name);

        if (isset($overrides[$method_name])) {
            $this->logger->info('Use override ' . $overrides[$method_name] . ' for ' . $method_name);
            $method = $this->getMethod($overrides[$method_name]);
        }
        $this->logger->debug('Call task ' . $task_name . ' on method ' . $method_name);

        if (method_exists($method, $task_name)) {
            $method->{$task_name}($configuration, $context);
            $in_context->mergeResults($context);
        } elseif (!$optional) {
            throw new TaskNotFoundInMethodException(
                'Could not find task `' . $task_name . '` in method `' . $method_name . '`'
            );
        }
    }

    /**
     * Call a task on a specifc method.
     *
     * @param string $method_name
     * @param string $task_name
     * @param HostConfig $configuration
     * @param TaskContextInterface $context
     *
     * @return TaskContextInterface
     * @throws MethodNotFoundException
     * @throws TaskNotFoundInMethodException
     */
    public function call(
        string $method_name,
        string $task_name,
        HostConfig $configuration,
        TaskContextInterface $context
    ): TaskContextInterface {
        $method = $this->getMethod($method_name);
        $this->preflight('preflight', $task_name, $configuration, $context);
        $this->callImpl($method, $task_name, $configuration, $context, false);
        $this->preflight('postflight', $task_name, $configuration, $context);

        return $context;
    }

    /**
     * Run preflight/ postflight-step
     *
     * @param string $step_name
     * @param string $task_name
     * @param HostConfig $configuration
     * @param TaskContextInterface $context
     *
     * @throws MethodNotFoundException
     */
    private function preflight(
        string $step_name,
        string $task_name,
        HostConfig $configuration,
        TaskContextInterface $context
    ) {
        foreach ($configuration['needs'] as $method_name) {
            $method = $this->getMethod($method_name);
            $method->{$step_name . "Task"}($task_name, $configuration, $context);
        }
    }

    /**
     * Get all registered methods.
     *
     * @return MethodInterface[]
     */
    public function all()
    {
        return $this->methods;
    }

    /**
     * Get a subset of methods.
     *
     * @param array $needs
     *
     * @return MethodInterface[]
     * @throws \Phabalicious\Exception\MethodNotFoundException
     */
    public function getSubset(Array $needs): array
    {
        return array_map(function ($elem) {
            return $this->getMethod($elem);
        }, $needs);
    }

    /**
     * @param array $needs
     * @param $interface
     *
     * @return array|\Phabalicious\Method\MethodInterface[]
     * @throws \Phabalicious\Exception\MethodNotFoundException*
     */
    public function getSubsetImplementing(array $needs, $interface): array
    {
        return array_filter(array_map(function ($elem) use ($interface) {
            $method = $this->getMethod($elem);
            if (is_a($method, $interface)) {
                return $method;
            }
            return false;
        }, $needs));
    }

    public function alter(array $needs, $func_name, AlterableDataInterface $data)
    {
        $fn = 'alter' . ucwords($func_name);
        foreach ($this->getSubset($needs) as $method) {
            if (method_exists($method, $fn)) {
                $method->{$fn}($data);
            }
        }
    }
}
