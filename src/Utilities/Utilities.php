<?php

namespace Phabalicious\Utilities;

use Phabalicious\Configuration\HostConfig;
use Phabalicious\Method\TaskContextInterface;

class Utilities
{

    const FALLBACK_VERSION = '3.2.14';

    const COMBINED_ARGUMENTS = 'combined';
    const UNNAMED_ARGUMENTS = 'unnamedArguments';

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
                $result["%$key%"] = (string)($value);
            }
        }
        return $result;
    }

    private static function expandVariablesImpl(string $prefix, array $variables, array &$result)
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                self::expandVariablesImpl($prefix . '.' . $key, $value, $result);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $result["%$prefix.$key%"] = (string)($value);
                }
            } else {
                $result["%$prefix.$key%"] = (string)($value);
            }
        }
    }

    public static function expandStrings(array $strings, array $replacements): array
    {
        if (empty($strings)) {
            return [];
        }
        $pattern = implode('|', array_filter(array_keys($replacements), 'preg_quote'));
        return self::expandStringsImpl($strings, $replacements, $pattern);
    }

    private static function expandStringsImpl(array $strings, array &$replacements, string $pattern)
    {
        $result = [];
        foreach ($strings as $key => $line) {
            if (is_array($line)) {
                $result[$key] = self::expandStringsImpl($line, $replacements, $pattern);
            } else {
                $result[$key] = preg_replace_callback('/' . $pattern . '/', function ($found) use ($replacements) {
                    return $replacements[$found[0]];
                }, $line);
            }
        }

        return $result;
    }


    public static function buildVariablesFrom(HostConfig $host_config, TaskContextInterface $context)
    {
        $variables = $context->get('variables', []);

        $variables = Utilities::mergeData($variables, [
            'context' => [
                'data' => $context->getData(),
                'results' => $context->getResults(),
            ],
            'host' => $host_config->raw(),
            'settings' => $context->getConfigurationService()
                ->getAllSettings(['hosts', 'dockerHosts']),
        ]);

        return $variables;
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
        if (strpos($subfolder, $rootFolder) === false) {
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

    public static function getRelativePath($from, $to)
    {
        // some compatibility fixes for Windows paths
        $from = is_dir($from) ? rtrim($from, '\/') . '/' : $from;
        $to = is_dir($to) ? rtrim($to, '\/') . '/' : $to;
        $from = str_replace('\\', '/', $from);
        $to = str_replace('\\', '/', $to);

        $from = explode('/', $from);
        $to = explode('/', $to);
        $relPath = $to;

        foreach ($from as $depth => $dir) {
            // find first non-matching dir
            if ($dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if ($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
            }
        }
        return implode('/', $relPath);
    }

    /**
     * @param $arguments_string
     * @return array
     */
    public static function parseArguments($arguments_string): array
    {
        $args = is_array($arguments_string) ? $arguments_string : explode(' ', $arguments_string);

        $unnamed_args = array_filter($args, function ($elem) {
            return strpos($elem, '=') === false;
        });
        $temp = array_filter($args, function ($elem) {
            return strpos($elem, '=') !== false;
        });
        $named_args = [];
        foreach ($temp as $value) {
            $a = explode('=', $value);
            $named_args[$a[0]] = $a[1];
        }

        $named_args = Utilities::mergeData($named_args, [
            self::COMBINED_ARGUMENTS => implode(' ', $unnamed_args),
            self::UNNAMED_ARGUMENTS => $unnamed_args,
        ]);
        return $named_args;
    }

    /**
     * Build an array suitable for InputOptions from an arbitrary array.
     *
     * @param array $array
     * @return array
     * @see Utilities::parseArguments()
     */
    public static function buildOptionsForArguments(array $array): array
    {
        $return = [];
        foreach ($array as $key => $value) {
            if ($key == Utilities::COMBINED_ARGUMENTS) {
                continue;
            } elseif ($key == Utilities::UNNAMED_ARGUMENTS) {
                foreach ($value as $item) {
                    $return[] = $item;
                }
            } else {
                $return[] = sprintf('%s=%s', $key, $value);
            }
        }
        return $return;
    }
}
