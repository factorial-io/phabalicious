<?php

namespace Phabalicious\Utilities;

class Utilities
{

    const FALLBACK_VERSION = '3.0.3';

    public static function mergeData(array $data, array $override_data): array
    {
        $result = $data;
        foreach ($override_data as $key => $value) {
            if (isset($data[$key])) {
                // Do a merge
                if (self::isAssocArray($data[$key]) && self::isAssocArray($value)) {
                    $result[$key] = self::mergeData($data[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            } else {
                // Just copy it.
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public static function expandVariables(array $variables): array
    {
        $result = [];
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                self::expandVariablesImpl($key, $value, $result);
            } else {
                $result["%$key%"] = (string) ($value);
            }
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

    public static function slugify($str, $replacement = '')
    {
        return preg_replace('/\s|\.|\,|_|\-|\//', $replacement, strtolower($str));
    }

    public static function isAssocArray($arr)
    {
        if (!is_array($arr) || array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function prependRootFolder($rootFolder, $subfolder)
    {
        if (strpos($rootFolder, $subfolder) === false) {
            return $rootFolder . $subfolder;
        }

        return $subfolder;
    }

    public static function cleanupString($identifier)
    {
        $identifier = trim($identifier);

        $filter = [
            ' ' => '-',
            '_' => '-',
            '/' => '-',
            '[' => '-',
            ']' => '',
        ];
        $identifier = strtr($identifier, $filter);

        $identifier = preg_replace(
            '/[^\\x{002D}\\x{0030}-\\x{0039}\\x{0041}-\\x{005A}\\x{005F}\\x{0061}-\\x{007A}\\x{00A1}-\\x{FFFF}]/u',
            '',
            $identifier
        );

        // Convert everything to lower case.
        return strtolower($identifier);
    }
}
