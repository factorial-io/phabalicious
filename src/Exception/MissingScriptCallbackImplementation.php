<?php
namespace Phabalicious\Exception;

use Throwable;

class MissingScriptCallbackImplementation extends \Exception
{
    public $callback;
    public $callbacks;

    public function __construct($callback, array $callbacks)
    {
        $this->callback = $callback;
        $this->callbacks = $callbacks;
        parent::__construct('Missing callback implementation for `' . $callback . '`');
    }

    public function getCallback()
    {
        return $this->callback;
    }
}
