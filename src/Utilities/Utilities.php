<?php

namespace Phabalicious\Utilities;

use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Exception\ArgumentParsingException;
use Phabalicious\Exception\UnknownReplacementPatternException;
use Phabalicious\Method\TaskContextInterface;
use Symfony\Component\Console\Input\InputInterface;

class Utilities
{
    public const FALLBACK_VERSION = '4.0.3';
    public const COMBINED_ARGUMENTS = 'combined';
    public const UNNAMED_ARGUMENTS = 'unnamedArguments';

    /**
     * Merge two arrays, elements of override_data will replace elements in data.
     *
     * Plain arrays, which are not associcative will get replaced, instead of merged.
     */
    public static function mergeData(array $data, array $override_data): array
    {
        $result = $data;
        foreach ($override_data as $key => $value) {
            if (isset($data[$key])) {
                // Do a merge
                if (is_array($data[$key])
                    && is_array($value)
                    && (self::isAssocArray($data[$key]) || self::isAssocArray($value))
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
     * Expand variables. Will create an array with replacement strings as key and their value.
     */
    public static function expandVariables(array $variables): array
    {
        $result = [];
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                self::expandVariablesImpl($key, $value, $result);
            } else {
                $result["%$key%"] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * Implementation details for expandVariables.
     */
    private static function expandVariablesImpl(string $prefix, array $variables, array &$result)
    {
        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                self::expandVariablesImpl($prefix.'.'.$key, $value, $result);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $result["%$prefix.$key%"] = (string) $value;
                }
            } else {
                $result["%$prefix.$key%"] = (string) $value;
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

        return empty($strings) ? '' : $strings[0];
    }

    /**
     * Expands an array of string and replace patterns.
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
                $result[$key] = preg_replace_callback('/'.$pattern.'/', function ($found) use ($replacements) {
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
     * @throws UnknownReplacementPatternException
     */
    public static function validateScriptCommands(array $commands, array $replacements): array
    {
        $validated = Utilities::validateReplacements($commands);
        if (true !== $validated) {
            throw new UnknownReplacementPatternException($validated, array_keys($replacements));
        }

        return array_map(function ($r) {
            return is_string($r) ? str_replace('\%', '%', $r) : $r;
        }, $commands);
    }

    /**
     * Validate for any remaining replacement strings.
     *
     * @param string[] $strings
     */
    public static function validateReplacements(array $strings): bool|ReplacementValidationError
    {
        foreach ($strings as $ndx => $line) {
            if (!is_string($line)) {
                continue;
            }
            // Ignore secrets, as they will be evaluated at a later stage.
            if (preg_match('/\\\?%secret\.[A-Za-z0-9\.\-_]*%/', $line)) {
                continue;
            }
            $matches = [];
            if (preg_match('/[^\\\]%[A-Za-z0-9\.\-_]*%/', $line, $matches)) {
                return new ReplacementValidationError($strings, $ndx, $matches[0]);
            }
            $matches = [];
            if (preg_match('/^%[A-Za-z0-9\.\-_]*%/', $line, $matches)) {
                return new ReplacementValidationError($strings, $ndx, $matches[0]);
            }
        }

        return true;
    }

    public static function buildVariablesFrom(HostConfig $host_config, TaskContextInterface $context): array
    {
        $variables = $context->get('variables', []);

        $variables = self::mergeData($variables, [
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

    public static function extractCallback($line): false|array
    {
        $p1 = strpos($line, '(');
        $p2 = strrpos($line, ')');

        if ((false === $p1) && (false === $p2)) {
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
     *             The string to parse
     *
     * @return array
     *               The array of arguments
     *
     * @throws ArgumentParsingException
     */
    public static function extractArguments($str): array
    {
        // If only one argument return early.
        if (!str_contains($str, ',')) {
            return [str_replace('"', '', $str)];
        }
        $result = [];
        $pos = 0;
        $done = false;
        do {
            // Find first non-space.
            while ($pos < strlen($str) && ctype_space($str[$pos])) {
                ++$pos;
            }
            if ($pos === strlen($str)) {
                $done = true;
            } elseif ('"' === $str[$pos]) {
                // Quoted string ahead, extract.
                $end_p = strpos($str, '"', $pos + 1);
                if (false === $end_p) {
                    throw new ArgumentParsingException(sprintf('Missing closing quote in %s', $str));
                }
                $found = substr($str, $pos + 1, $end_p - $pos - 1);
                $result[] = $found;
                // Advance to next comma or finish.
                $comma_p = strpos($str, ',', $end_p + 1);
                if (false === $comma_p) {
                    // Seems we are finished.
                    $done = true;
                } else {
                    $pos = $comma_p;
                }
            } elseif (',' === $str[$pos]) {
                // Comma, move one char forward.
                ++$pos;
                continue;
            } else {
                // Handle argument without quotes.
                $end_p = strpos($str, ',', $pos);
                if (false === $end_p) {
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
        if ($data instanceof Node) {
            return $data->getProperty($key, $default_value);
        }

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

    public static function setProperty(&$data, string $dotted_key, $new_value): void
    {
        $keys = explode('.', $dotted_key);

        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                throw new \InvalidArgumentException(sprintf('Could not find key %s in data!', $dotted_key));
            }
            $data = &$data[$key];
        }

        $data = $new_value;
    }

    public static function slugify($str, $replacement = ''): array|string|null
    {
        return preg_replace('/\s|\.|\,|_|\-|:|\//', $replacement, strtolower($str));
    }

    /**
     * Check if $arr is an associative array.
     */
    public static function isAssocArray($arr): bool
    {
        if (!is_array($arr) || [] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function prependRootFolder($rootFolder, $subfolder): string
    {
        if (!str_contains($subfolder, $rootFolder)) {
            return $rootFolder.$subfolder;
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
            '/[^\\x{002D}\\x{002E}}\\x{0030}-\\x{0039}\\x{0041}-'.
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
        $from = is_dir($from) ? rtrim($from, '\/').'/' : $from;
        $to = is_dir($to) ? rtrim($to, '\/').'/' : $to;
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
                    $relPath[0] = './'.$relPath[0];
                }
            }
        }

        return implode('/', $relPath);
    }

    public static function parseArguments($arguments_string): array
    {
        $args = is_array($arguments_string) ? $arguments_string : explode(' ', $arguments_string);

        $unnamed_args = array_filter($args, static function ($elem) {
            return !str_contains($elem, '=');
        });
        $temp = array_filter($args, static function ($elem) {
            return str_contains($elem, '=');
        });
        $named_args = [];
        foreach ($temp as $value) {
            $a = explode('=', $value, 2);
            $named_args[$a[0]] = $a[1];
        }

        $named_args = self::mergeData($named_args, [
            self::COMBINED_ARGUMENTS => implode(' ', $unnamed_args),
            self::UNNAMED_ARGUMENTS => $unnamed_args,
        ]);

        return $named_args;
    }

    /**
     * Build an array suitable for InputOptions from an arbitrary array.
     *
     * @see Utilities::parseArguments()
     */
    public static function buildOptionsForArguments(array $array): array
    {
        $return = [];
        foreach ($array as $key => $value) {
            if (Utilities::COMBINED_ARGUMENTS == $key) {
                continue;
            } elseif (Utilities::UNNAMED_ARGUMENTS == $key) {
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
        return bin2hex(openssl_random_pseudo_bytes(4)).'-'.
            bin2hex(openssl_random_pseudo_bytes(2)).'-'.
            bin2hex(openssl_random_pseudo_bytes(2)).'-'.
            bin2hex(openssl_random_pseudo_bytes(2)).'-'.
            bin2hex(openssl_random_pseudo_bytes(6));
    }

    public static function getReplacements(array $tokens): array
    {
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['%'.$key.'%'] = $value;
        }

        return $replacements;
    }

    public static function pushKeysAsDotNotation(array $data, &$return, $levels = []): void
    {
        foreach ($data as $key => $value) {
            $new_levels = $levels;
            $new_levels[] = $key;
            if (is_array($value)) {
                self::pushKeysAsDotNotation($value, $return, $new_levels);
            } else {
                $return[] = implode('.', $new_levels);
            }
        }
    }

    /**
     * Get the current users home directory.
     */
    public static function getUserFolder(): string
    {
        $uid = posix_getuid();

        return posix_getpwuid($uid)['dir'];
    }

    /**
     * Check if the given named option is set.
     *
     * @param InputInterface $input
     *                              The input
     * @param string         $name
     *                              The name of the option
     */
    public static function hasBoolOptionSet(InputInterface $input, string $name): bool
    {
        if (!$input->hasOption($name)) {
            return false;
        }
        $option = $input->getOption($name);

        // Testing for valueless options is tricky in symfony. That is why we test for
        // `is_null` (has no option value, e.g. `--force`) or `!empty()`, e.g. `--force=1`
        return is_null($option) || !empty($option);
    }

    public static function camel2dashed($string): string
    {
        return strtolower(preg_replace('/([A-Z])/', '-$1', $string));
    }

    public static function toUpperSnakeCase($string): string
    {
        return strtoupper(str_replace('-', '_', self::camel2dashed($string)));
    }

    /**
     * Remove any beta or alpha string from a version string.
     */
    public static function getNextStableVersion(string $version): string
    {
        $exploded = explode('.', $version);
        if (count($exploded) < 3) {
            return $version;
        }

        if (str_contains($exploded[2], '-beta') || str_contains($exploded[2], '-alpha')) {
            $exploded[2] = str_replace('-beta', '', $exploded[2]);
            $exploded[2] = str_replace('-alpha', '', $exploded[2]);
            unset($exploded[3]);

            return implode('.', $exploded);
        }

        return $version;
    }

    public static function getTempNamePrefixFromString(string $str, $prefix = 'phab'): string
    {
        return $prefix.'-'.md5($str.mt_rand());
    }

    public static function getTempNamePrefix($hostconfig): string
    {
        return self::getTempNamePrefixFromString($hostconfig->getConfigName());
    }

    public static function getTempFileName(HostConfig $host_config, $str): string
    {
        return
            $host_config->get('tmpFolder', sys_get_temp_dir()).'/'.
            bin2hex(random_bytes(8)).'--'.
            basename($str);
    }

    public static function getTempFolder(HostConfig $hostConfig, string $name): string
    {
        $tempDir = $hostConfig->get('tmpFolder', sys_get_temp_dir());
        $uniqueFolderName = uniqid($name, true);
        $uniqueFolderPath = $tempDir.DIRECTORY_SEPARATOR.$uniqueFolderName;
        if (mkdir($uniqueFolderPath, 0777, true) || is_dir($uniqueFolderPath)) {
            return $uniqueFolderPath;
        }

        throw new \RuntimeException('Could not create temporary folder '.$uniqueFolderPath);
    }

    /**
     * Custom parse_url implementation, as parse_url does not support phar-scheme.
     */
    public static function parseUrl($url)
    {
        if (self::isPharUrl($url)) {
            return [
                'scheme' => 'phar',
                'path' => str_replace('phar://', '//', $url),
            ];
        }

        return parse_url($url);
    }

    public static function resolveRelativePaths(string $url): string
    {
        $result = self::parseUrl($url);
        $filename = $result['path'];
        $path = [];
        $parts = explode('/', $filename);
        $root = '';
        if (empty($result['host']) && !empty($parts[0]) && '.' == $parts[0][0]) {
            $root = $parts[0];
            array_shift($parts);
        }

        foreach ($parts as $part) {
            if ('.' === $part || '' === $part) {
                continue;
            }

            if ('..' !== $part) {
                array_push($path, $part);
            } elseif (count($path) > 0) {
                array_pop($path);
            } else {
                throw new \Exception('Climbing above the root is not permitted.');
            }
        }

        array_unshift($path, $root);

        $result['path'] = implode('/', $path);

        return self::buildUrl($result);
    }

    public static function buildUrl($components): string
    {
        $url = '';
        if (!empty($components['scheme'])) {
            $url .= $components['scheme'].'://';
        }
        if (!empty($components['username']) && !empty($components['password'])) {
            $url .= $components['username'].':'.$components['password'].'@';
        }
        if (!empty($components['host'])) {
            $url .= $components['host'];
        }
        if (!empty($components['port'])) {
            $url .= ':'.$components['port'];
        }
        if (!empty($components['path'])) {
            $url .= $components['path'];
        }
        if (!empty($components['query'])) {
            $url .= '?'.http_build_query($components['query']);
        }
        if (!empty($components['fragment'])) {
            $url .= '#'.$components['fragment'];
        }

        return $url;
    }

    /**
     * Returns true if url is a http url.
     */
    public static function isHttpUrl($url): bool
    {
        return str_starts_with($url, 'http') && str_contains($url, '://');
    }

    /**
     * Returns true if url is a phar url.
     */
    public static function isPharUrl($url): bool
    {
        return str_starts_with($url, 'phar') && str_contains($url, '://');
    }
}
