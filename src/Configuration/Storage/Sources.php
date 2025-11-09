<?php

namespace Phabalicious\Configuration\Storage;

class Sources
{
    protected static $sources = [];

    public static function getSource($source)
    {
        if ($source instanceof Source) {
            return $source;
        }
        if (!isset(self::$sources[$source])) {
            self::$sources[$source] = new Source($source);
        }

        return self::$sources[$source];
    }
}
