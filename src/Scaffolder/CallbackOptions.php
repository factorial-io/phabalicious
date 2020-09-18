<?php


namespace Phabalicious\Scaffolder;

use Phabalicious\Method\AlterableDataInterface;
use Phabalicious\Scaffolder\Callbacks\AlterJsonFileCallback;
use Phabalicious\Scaffolder\Callbacks\AssertContainsCallback;
use Phabalicious\Scaffolder\Callbacks\AssertFileCallback;
use Phabalicious\Scaffolder\Callbacks\AssertNonZeroCallback;
use Phabalicious\Scaffolder\Callbacks\AssertZeroCallback;
use Phabalicious\Scaffolder\Callbacks\ConfirmCallback;
use Phabalicious\Scaffolder\Callbacks\LogMessageCallback;

class CallbackOptions implements AlterableDataInterface
{
    protected $callbacks = [];

    public function addDefaultCallbacks(): CallbackOptions
    {
        $this
            ->addCallback(ConfirmCallback::getName(), [new ConfirmCallback(), 'handle'])
            ->addCallback(LogMessageCallback::getName(), [new LogMessageCallback(), 'handle'])
            ->addCallback(AssertNonZeroCallback::getName(), [new AssertNonZeroCallback(), 'handle'])
            ->addCallback(AssertZeroCallback::getName(), [new AssertZeroCallback(), 'handle'])
            ->addCallback(AssertFileCallback::getName(), [new AssertFileCallback(), 'handle'])
            ->addCallback(AssertContainsCallback::getName(), [new AssertContainsCallback(), 'handle'])
            ->addCallback(AlterJsonFileCallback::getName(), [new AlterJsonFileCallback(), 'handle']);

        return $this;
    }

    public function addCallback($name, $callable): CallbackOptions
    {
        $this->callbacks[$name] = $callable;
        return $this;
    }

    public function getCallbacks(): array
    {
        return $this->callbacks;
    }
}
