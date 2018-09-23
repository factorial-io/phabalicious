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

    public static function extractCallback($line)
    {
        $p1 = strpos($line, '(');
        $p2 = strpos($line, ')');

        if (($p1 === false) && ($p2 === false)) {
            return false;
        }

        $callback_name = substr($line, 0, $p1);
        $args = substr($line, $p1+1, $p2 - $p1 - 1);
        $args = array_map('trim', explode(',', $args));
        return [ $callback_name, $args];
    }

    public static function getProperty($data, string $key, $default_value = null)
    {
        $value = $default_value;
        $keys = explode('.', $key);
        $first_run = true;
        foreach ($keys as $sub_key) {
            if ($first_run) {
                $value = $data;
                $first_run = false;
            }
            if (isset($value[$sub_key])) {
                $value = $value[$sub_key];
            } else {
                return $default_value;
            }
        }

        return $value;
    }
}
