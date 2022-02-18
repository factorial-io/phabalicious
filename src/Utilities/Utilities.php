<?php

namespace Phabalicious\Utilities;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\ArgumentParsingException;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Method\TaskContextInterface;
use Symfony\Component\Console\Input\InputInterface;

class Utilities
{

    const FALLBACK_VERSION = '3.8.0';
    const COMBINED_ARGUMENTS = 'combined';
    const UNNAMED_ARGUMENTS = 'unnamedArguments';

    /**
     * Merge two arrays, elements of override_data will replace elements in data.
     *
     * Plain arrays, which are not associcative will get replaced, instead of merged.
     *
     * @param array $data
     * @param array $override_data
     *
     * @return array
     */
    public static function mergeData(array $data, array $override_data): array
    {
        $result = $data;
        foreach ($override_data as $key => $value) {
            if (isset($data[$key])) {
                // Do a merge
                if (is_array($data[$key]) &&
                    is_array($value) &&
                    (self::isAssocArray($data[$key]) || self::isAssocArray($value))
                ) {
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

    /**
     * Expand variables. Will create an array with replacement strings as key and their value
     *
     * @param array $variables
     *
     * @return array
     */
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

    /**
     * Implementation details for expandVariables.
     *
     * @param string $prefix
     * @param array $variables
     * @param array $result
     */
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

    public static function getGlobalReplacements(ConfigurationService $config)
    {
        return [
            'userFolder' => self::getUserFolder(),
            'cwd' => getcwd(),
            'fabfileLocation' => $config->getFabfileLocation(),
            'fabfilePath' => $config->getFabfilePath(),
        ];
    }

    public static function expandString($string, array $replacements, array $ignore_list = []): string
    {
        return self::expandStrings([$string], $replacements, $ignore_list)[0];
    }

    public static function expandAndValidateString($string, array $replacements, array $ignore_list = []): string
    {
        $strings = self::expandStrings([$string], $replacements, $ignore_list);
        $strings = self::validateScriptCommands($strings, $replacements);

        return empty($strings) ? "" : $strings[0];
    }

    /**
     * Expands an array of string and replace patterns.
     *
     * @param array $strings
     * @param array $replacements
     * @param array $ignore_list
     *
     * @return array
     */
    public static function expandStrings(array $strings, array $replacements, array $ignore_list = []): array
    {
        if (empty($strings)) {
            return [];
        }

        $chunked_patterns = array_chunk(array_keys($replacements), 25);
        $result = $strings;

        foreach ($chunked_patterns as $chunk) {
            $pattern = implode('|', array_map(function ($e) {
                return preg_quote($e, '/');
            }, $chunk));

            $result = self::expandStringsImpl($result, $replacements, $pattern, $ignore_list);
        }

        return $result;
    }

    private static function expandStringsImpl(array $strings, array &$replacements, string $pattern, array $ignore_list)
    {
        $result = [];
        foreach ($strings as $key => $line) {
            if (in_array($key, $ignore_list)) {
                $result[$key] = $line;
            } elseif (is_array($line)) {
                $result[$key] = self::expandStringsImpl($line, $replacements, $pattern, $ignore_list);
            } elseif (is_string($line)) {
                $result[$key] = preg_replace_callback('/' . $pattern . '/', function ($found) use ($replacements) {
                    return $replacements[$found[0]];
                }, $line);
            } else {
                $result[$key] = $line;
            }
        }

        return $result;
    }

    /**
     * Validate an array of script commands and unescape any escaped percentages.
     *
     * @param array $commands
     * @param array $replacements
     *
     * @return array
     * @throws \Phabalicious\Exception\UnknownReplacementPatternException
     */
    public static function validateScriptCommands(array $commands, array $replacements): array
    {
        $validated = Utilities::validateReplacements($commands);
        if ($validated !== true) {
            throw new UnknownReplacementPatternException($validated, array_keys($replacements));
        }

        return array_map(function ($r) {
            return str_replace('\%', '%', $r);
        }, $commands);
    }

    /**
     * Validate for any remaining replacement strings.
     *
     * @param string[] $strings
     * @return true|string
     */
    public static function validateReplacements(array $strings)
    {
        foreach ($strings as $line) {
            if (preg_match('/[^\\\]%[A-Za-z0-9\.-_]*%/', $line)) {
                return $line;
            }
            if (preg_match('/^%[A-Za-z0-9\.-_]*%/', $line)) {
                return $line;
            }
        }
        return true;
    }

    public static function buildVariablesFrom(HostConfig $host_config, TaskContextInterface $context)
    {
        $variables = $context->get('variables', []);

        $variables = Utilities::mergeData($variables, [
            'context' => [
                'data' => $context->getData(),
                'results' => $context->getResults(),
            ],
            'host' => $host_config->asArray(),
            'settings' => $context->getConfigurationService()
                ->getAllSettings(['hosts', 'dockerHosts']),
        ]);
        if (!empty($host_config['docker']['configuration'])) {
            $docker_config = $context
                ->getConfigurationService()
                ->getDockerConfig($host_config['docker']['configuration']);
            if ($docker_config) {
                $variables['dockerHost'] = $docker_config->asArray();
            }
        }

        return $variables;
    }

    public static function extractCallback($line)
    {
        $p1 = strpos($line, '(');
        $p2 = strrpos($line, ')');

        if (($p1 === false) && ($p2 === false)) {
            return false;
        }

        $callback_name = substr($line, 0, $p1);
        $arg_string = trim(substr($line, $p1 + 1, $p2 - $p1 - 1));
        $args = self::extractArguments($arg_string);
        return [$callback_name, $args];
    }

    /**
     * Extract callback arguments from a string, support quoted string as arguments.
     *
     * @param $str
     *   The string to parse.
     * @return array
     *   The array of arguments.
     *
     * @throws ArgumentParsingException
     */
    public static function extractArguments($str)
    {
        // If only one argument return early.
        if (strpos($str, ',') === false) {
            return [ str_replace('"', '', $str) ];
        }
        $result = [];
        $pos = 0;
        $done = false;
        do {
            // Find first non-space.
            while ($pos < strlen($str) && ctype_space($str[$pos])) {
                $pos++;
            }
            if ($pos == strlen($str)) {
                $done = true;
            } elseif ($str[$pos] == '"') {
                // Quoted string ahead, extract.
                $end_p = strpos($str, '"', $pos+1);
                if ($end_p === false) {
                    throw new ArgumentParsingException(sprintf('Missing closing quote in %s', $str));
                }
                $found = substr($str, $pos+1, $end_p - $pos - 1);
                $result[] = $found;
                // Advance to next comma or finish.
                $comma_p = strpos($str, ',', $end_p+1);
                if ($comma_p === false) {
                    // Seems we are finished.
                    $done = true;
                } else {
                    $pos = $comma_p;
                }
            } elseif ($str[$pos] == ',') {
                // Comma, move one char forward.
                $pos++;
                continue;
            } else {
                // Handle argument without quotes.
                $end_p = strpos($str, ',', $pos);
                if ($end_p === false) {
                    // Seems like this is the last one
                    $end_p = strlen($str);
                }

                $found = substr($str, $pos, $end_p - $pos);
                $result[] = $found;
                $pos = $end_p;
            }
        } while (!$done);

        return $result;
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

    public static function setProperty(&$data, string $dotted_key, $new_value)
    {
        $keys = explode('.', $dotted_key);

        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                throw new \InvalidArgumentException(sprintf("Could not find key %s in data!", $dotted_key));
            }
            $data = &$data[$key];
        }

        $data = $new_value;
    }

    public static function slugify($str, $replacement = '')
    {
        return preg_replace('/\s|\.|\,|_|\-|:|\//', $replacement, strtolower($str));
    }

    /**
     * Check if $arr is an associative array.
     *
     * @param mixed $arr
     *
     * @return bool
     */
    public static function isAssocArray($arr): bool
    {
        if (!is_array($arr) || array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function prependRootFolder($rootFolder, $subfolder): string
    {
        if (strpos($subfolder, $rootFolder) === false) {
            return $rootFolder . $subfolder;
        }

        return $subfolder;
    }

    public static function cleanupString($identifier): string
    {
        $identifier = trim($identifier);

        $filter = [
            ' ' => '-',
            '/' => '-',
            '[' => '-',
            ']' => '',
        ];
        $identifier = strtr($identifier, $filter);


        $identifier = preg_replace(
            '/[^\\x{002D}\\x{002E}}\\x{0030}-\\x{0039}\\x{0041}-' .
            '\\x{005A}\\x{005F}\\x{0061}-\\x{007A}\\x{00A1}-\\x{FFFF}]/u',
            '',
            $identifier
        );

        // Convert everything to lower case.
        return strtolower($identifier);
    }

    public static function getRelativePath($from, $to): string
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


    public static function generateUUID(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(4)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(2)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(2)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(2)) . '-' .
            bin2hex(openssl_random_pseudo_bytes(6));
    }

    /**
     * @param array $tokens
     * @return array
     */
    public static function getReplacements(array $tokens): array
    {
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['%' . $key . '%'] = $value;
        }
        return $replacements;
    }

    public static function pushKeysAsDotNotation(array $data, &$return, $levels = [])
    {
        foreach ($data as $key => $value) {
            $new_levels = $levels;
            $new_levels[] = $key;
            if (is_array($value)) {
                self::pushKeysAsDotNotation($value, $return, $new_levels);
            } else {
                $return[] =  implode('.', $new_levels);
            }
        }
    }

    /**
     * Get the current users home directory.
     *
     * @return string
     */
    public static function getUserFolder(): string
    {
        $uid = posix_getuid();
        return posix_getpwuid($uid)['dir'];
    }

    /**
     * Check if the given named option is set.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input.
     * @param string $name
     *   The name of the option.
     *
     * @return bool
     */
    public static function hasBoolOptionSet(InputInterface $input, string $name): bool
    {
        $option = $input->getOption($name);

        // Testing for valueless options is tricky in symfony. That is why we test for
        // `is_null` (has no option value, e.g. `--force`) or `!empty()`, e.g. `--force=1`
        return is_null($option) || !empty($option);
    }

    public static function camel2dashed($string)
    {
        return strtolower(preg_replace('/([A-Z])/', '-$1', $string));
    }

    public static function toUpperSnakeCase($string)
    {
        return strtoUpper(str_replace('-', '_', self::camel2dashed($string)));
    }

    /**
     * Remove any beta or alpha string from a version string.
     */
    public static function getNextStableVersion(string $version)
    {
        $exploded = explode(".", $version);
        if (count($exploded) < 3) {
            return $version;
        }

        if (str_contains($exploded[2], '-beta') || str_contains($exploded[2], '-alpha')) {
            $exploded[2] = str_replace('-beta', '', $exploded[2]);
            $exploded[2] = str_replace('-alpha', '', $exploded[2]);
            unset($exploded[3]);

            return implode(".", $exploded);
        }

        return $version;
    }

    public static function getTempNamePrefix($hostconfig): string
    {
        return 'phab-' . md5($hostconfig->getConfigName() . mt_rand());
    }

    public static function getTempFileName(HostConfig $host_config, $str): string
    {
        return
            $host_config->get('tmpFolder', '/tmp') . '/' .
            bin2hex(random_bytes(8)) . '--' .
            basename($str);
    }

    public static function resolveRelativePaths(string $url): string
    {
        $result = parse_url($url);
        $filename = $result['path'];
        $path = [];
        $parts = explode('/', $filename);
        $root = '';
        if (empty($result['host']) && !empty($parts[0]) && $parts[0][0] == '.') {
            $root = $parts[0];
            array_shift($parts);
        }

        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }

            if ($part !== '..') {
                array_push($path, $part);
            } elseif (count($path) > 0) {
                array_pop($path);
            } else {
                throw new \Exception('Climbing above the root is not permitted.');
            }
        }

        array_unshift($path, $root);

        $result['path'] = join('/', $path);

        return self::buildUrl($result);
    }

    public static function buildUrl($components)
    {
        $url = '';
        if (!empty($components['scheme'])) {
            $url .= $components['scheme'] . '://';
        }
        if (!empty($components['username']) && !empty($components['password'])) {
            $url .= $components['username'] . ':' . $components['password'] . '@';
        }
        if (!empty($components['scheme'])) {
            $url .= $components['host'];
        }
        if (!empty($components['port'])) {
            $url .= ':' . $components['port'];
        }
        if (!empty($components['path'])) {
            $url .= $components['path'];
        }
        if (!empty($components['query'])) {
            $url .= '?' . http_build_query($components['query']);
        }
        if (!empty($components['fragment'])) {
            $url .= '#' . $components['fragment'];
        }
        return $url;
    }
}
