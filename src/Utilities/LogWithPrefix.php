<?php

namespace Phabalicious\Utilities;

use Psr\Log\LoggerInterface;

class LogWithPrefix implements LoggerInterface
{
    protected $logger;
    protected $prefix;

    public function __construct(LoggerInterface $logger, $prefix)
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    private function addPrefix($message)
    {
        return sprintf('[%s] %s', $this->prefix, $message);
    }

    public function emergency($message, array $context = []): void
    {
        $this->logger->emergency($this->addPrefix($message), $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->logger->alert($this->addPrefix($message), $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->logger->critical($this->addPrefix($message), $context);
    }

    public function error($message, array $context = []): void
    {
        $this->logger->error($this->addPrefix($message), $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->logger->warning($this->addPrefix($message), $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->logger->notice($this->addPrefix($message), $context);
    }

    public function info($message, array $context = []): void
    {
        $this->logger->info($this->addPrefix($message), $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->logger->debug($this->addPrefix($message), $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $this->addPrefix($message), $context);
    }
}
