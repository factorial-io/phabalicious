<?php

namespace Phabalicious\Method;

use Phabalicious\Configuration\ConfigurationService;
use Symfony\Component\Console\Output\OutputInterface;

interface TaskContextInterface {

    public function set(string $key, $data);

    public function get(string $key, $default = null);

    public function setOutput(OutputInterface $output);

    public function getOutput(): OutputInterface;

    public function setConfigurationService(ConfigurationService $service);

    public function getConfigurationService(): ConfigurationService;

}

