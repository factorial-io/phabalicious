<?php

namespace Phabalicious\Utilities;

interface AvailableMethodsAndCommandsPluginInterface
{
    /**
     * Get all the methods of the plugin.
     */
    public static function getMethods(): array;

    /**
     * Get all the methods of the plugin.
     */
    public static function getCommands(): array;
}
