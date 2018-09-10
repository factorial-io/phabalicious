<?php

namespace Phabalicious\Utilities;

use Wikimedia\Composer\Merge\NestedArray;

class Utilities
{

    public static function mergeData(array $data, array $override_data): array
    {
        return NestedArray::mergeDeep($data, $override_data);
    }
}