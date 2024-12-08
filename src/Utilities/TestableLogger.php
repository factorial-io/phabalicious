<?php

namespace Phabalicious\Utilities;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class TestableLogger implements LoggerInterface
{
    private $storage = [];

    /**
     * System is unusable.
     *
     * @param string $message
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        if (!isset($this->storage[$level])) {
            $this->storage[$level] = [];
        }
        $this->storage[$level][] = $message;
    }

    public function getStorage($level)
    {
        return $this->storage[$level];
    }

    public function containsMessage($level, $message)
    {
        return isset($this->storage[$level])
            && array_reduce($this->storage[$level], function ($carry, $item) use ($message) {
                return 0 !== strpos($message, $item) ? true : $carry;
            }, false);
    }
}
