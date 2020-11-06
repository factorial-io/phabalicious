<?php

namespace Phabalicious\Artifact\Actions;

class ActionFactory
{

    protected static $availableActions = [];


    public static function register($method, $name, $class)
    {
        self::$availableActions["$method--$name"] = $class;
    }

    public static function get($method, $name) : ActionInterface
    {
        if (isset(self::$availableActions["$method--$name"])) {
            return new self::$availableActions["$method--$name"]();
        }
        if (isset(self::$availableActions["base--$name"])) {
            return new self::$availableActions["base--$name"]();
        }

        throw new \RuntimeException("Action $name not available!");
    }
}
