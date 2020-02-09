<?php

namespace Phabalicious\Utilities;

interface PluginInterface
{
    /**
     * Get the name of the plugin.
     */
    public static function getName();

    /**
     * Which (semantic) version is needed at minumum for this plugin.
     *
     * @return string
     */
    public static function requires();
}
