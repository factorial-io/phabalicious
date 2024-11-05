<?php

namespace Phabalicious\Tests;

use Psr\Log\LoggerInterface;

class SimpleLogger implements LoggerInterface
{

    public function emergency($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function alert($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function critical($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function error($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function warning($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function notice($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function info($message, array $context = array()): void
    {
        $this->output($message);
    }

    public function debug($message, array $context = array()): void
    {
    }

    public function log($level, $message, array $context = array()): void
    {
        $this->output($message);
    }

    protected function output(string $message)
    {
        echo $message . "\n";
    }
}
