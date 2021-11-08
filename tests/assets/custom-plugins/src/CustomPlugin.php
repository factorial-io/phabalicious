<?php

namespace Phabalicious\CustomPlugin;

use Phabalicious\Utilities\AvailableMethodsAndCommandsPluginInterface;
use Phabalicious\Utilities\PluginInterface;

class CustomPlugin implements PluginInterface, AvailableMethodsAndCommandsPluginInterface
{

    public static function getName(): string
    {
        return "CustomPlugin";
    }

    public static function requires(): string
    {
        return "3.7.0";
    }

    public static function getMethods(): array
    {
        return [
            CustomMethod::class
        ];
    }

    public static function getCommands(): array
    {
        return [
            CustomCommand::class
        ];
    }
}
