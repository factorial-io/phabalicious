<?php


namespace Phabalicious\Scaffolder;

use Phabalicious\Method\AlterableDataInterface;
use Phabalicious\Scaffolder\Callbacks\AlterJsonFileCallback;
use Phabalicious\Scaffolder\Callbacks\AlterYamlFileCallback;
use Phabalicious\Scaffolder\Callbacks\AssertContainsCallback;
use Phabalicious\Scaffolder\Callbacks\AssertFileCallback;
use Phabalicious\Scaffolder\Callbacks\AssertNonZeroCallback;
use Phabalicious\Scaffolder\Callbacks\AssertZeroCallback;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;
use Phabalicious\Scaffolder\Callbacks\ConfirmCallback;
use Phabalicious\Scaffolder\Callbacks\LogMessageCallback;
use Phabalicious\Scaffolder\Callbacks\ScaffoldCallback;
use Phabalicious\Scaffolder\Callbacks\SetDirectoryCallback;

class CallbackOptions implements AlterableDataInterface
{
    protected $callbacks = [];

    public function addDefaultCallbacks(): CallbackOptions
    {
        $this
            ->addCallback(new ConfirmCallback())
            ->addCallback(new LogMessageCallback())
            ->addCallback(new AssertNonZeroCallback())
            ->addCallback(new AssertZeroCallback())
            ->addCallback(new AssertFileCallback())
            ->addCallback(new AssertContainsCallback())
            ->addCallback(new SetDirectoryCallBack())
            ->addCallback(new AlterYamlFileCallback())
            ->addCallback(new AlterJsonFileCallback())
            ->addCallback(new ScaffoldCallback());

        return $this;
    }

    public function addCallback(CallbackInterface $callback): CallbackOptions
    {
        $this->callbacks[$callback::getName()] = $callback;
        return $this;
    }

    public function getCallbacks(): array
    {
        return $this->callbacks;
    }
}
