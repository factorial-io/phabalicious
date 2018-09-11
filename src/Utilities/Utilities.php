<?php

namespace Phabalicious\Utilities;

use Wikimedia\Composer\Merge\NestedArray;

class Utilities
{

    public static function mergeData(array $data, array $override_data): array
    {
        return NestedArray::mergeDeep($data, $override_data);
    }

    public static function expandVariables(array $variables): array
    {
        $result = [];
        foreach ($variables as $key => $value) {
            self::expandVariablesImpl($key, $value, $result);
        }
        return $result;
    }

    private static function expandVariablesImpl(string $prefix, array $variables, array &$result)
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                self::expandVariablesImpl($prefix . '.' . $key, $value, $result);
            } else {
                $result["%$prefix.$key%"] = (string) ($value);
            }
        }
    }

    public static function expandStrings(array $strings, array $replacements): array
    {
        if (empty($strings)) {
            return [];
        }
        $result = [];
        $pattern = implode('|', array_filter(array_keys($replacements), 'preg_quote'));
        foreach ($strings as $key => $line) {
             $result[$key] = preg_replace_callback('/' . $pattern . '/', function ($found) use ($replacements) {
                return $replacements[$found[0]];
             }, $line);
        }

        return $result;
    }


}
