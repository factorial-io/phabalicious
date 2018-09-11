<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Symfony\Component\Console\Output\OutputInterface;

class TaskContext implements TaskContextInterface
{
    private $data = [];

    private $output;

    private $configurationService;

    public function __construct(ConfigurationService $service, OutputInterface $output)
    {
        $this->setOutput($output);
        $this->setConfigurationService($service);
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
         return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function setConfigurationService(ConfigurationService $service)
    {
        $this->configurationService = $service;
    }

    public function getConfigurationService(): ConfigurationService
    {
        return $this->configurationService;
    }
}