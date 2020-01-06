<?php

namespace Phabalicious\Utilities;

/**
 * This class will set a property of an object, and when the class gets out of scope,
 * the property will be restored to its previous value.
 */
class SetAndRestoreObjProperty
{
    private $fn;
    private $savedValue;

    public function __construct($prop_name, $obj, $new_value)
    {
        $getter = function () use ($prop_name, $obj) {
            return $obj->{$prop_name};
        };
        $getter = \Closure::bind($getter, null, $obj);

        $setter = function ($value) use ($prop_name, $obj) {
            $obj->{$prop_name} = $value;
        };
        $setter = \Closure::bind($setter, null, $obj);

        $this->savedValue = $getter();
        $setter($new_value);

        $this->fn = $setter;
    }

    public function __destruct()
    {
        $closure = $this->fn;
        $closure($this->savedValue);
    }
}
