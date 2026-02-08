<?php

namespace Zaplane\Utils;

if (!defined('ABSPATH')) exit;

class VariableExtractor
{
    /**
     * Extract variables from an output array with auto-detected types
     *
     * @param mixed $data The output data to extract variables from
     * @param string $prefix Optional prefix for nested keys
     * @return array Array of variable definitions with key, type, and sample
     */
    public static function extract($data, string $prefix = ''): array
    {
        // Handle non-array data
        if (!is_array($data)) {
            if ($data === null) {
                return [];
            }
            return [
                [
                    'key' => $prefix ?: 'value',
                    'type' => self::detectType($data),
                    'sample' => self::getSample($data),
                ]
            ];
        }

        if (empty($data)) {
            return [];
        }

        $variables = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                // Check if it's an indexed array (list) or associative array (object)
                if (self::isIndexedArray($value)) {
                    $variables[] = [
                        'key' => $fullKey,
                        'type' => 'array',
                        'sample' => self::getSample($value),
                    ];

                    // If array has items, extract the first item's structure
                    if (!empty($value) && is_array($value[0])) {
                        $nestedVars = self::extract($value[0], "{$fullKey}[]");
                        $variables = array_merge($variables, $nestedVars);
                    }
                } else {
                    // Associative array - recurse into it
                    $nestedVars = self::extract($value, $fullKey);
                    $variables = array_merge($variables, $nestedVars);
                }
            } else {
                $variables[] = [
                    'key' => $fullKey,
                    'type' => self::detectType($value),
                    'sample' => self::getSample($value),
                ];
            }
        }

        return $variables;
    }

    /**
     * Detect the type of a value
     */
    private static function detectType($value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            // Check for special string types
            if (self::isDateString($value)) {
                return 'datetime';
            }

            if (self::isEmailString($value)) {
                return 'email';
            }

            if (self::isUrlString($value)) {
                return 'url';
            }

            if (self::isHtmlString($value)) {
                return 'html';
            }

            return 'string';
        }

        return 'mixed';
    }

    /**
     * Check if an array is indexed (list) or associative
     */
    private static function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Get a sample value for display
     */
    private static function getSample($value): mixed
    {
        if (is_array($value)) {
            if (empty($value)) {
                return [];
            }

            // For arrays, return count indicator
            return count($value) . ' items';
        }

        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }

        return $value;
    }

    /**
     * Check if string looks like a date
     */
    private static function isDateString(string $value): bool
    {
        // Common date patterns
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}/',  // 2024-01-01
            '/^\d{2}\/\d{2}\/\d{4}/', // 01/01/2024
            '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', // ISO datetime
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string looks like an email
     */
    private static function isEmailString(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if string looks like a URL
     */
    private static function isUrlString(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if string contains HTML
     */
    private static function isHtmlString(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    /**
     * Convert output to a flat key-value map for condition building
     *
     * @param array $data The output data
     * @param string $prefix Optional prefix for nested keys
     * @return array Flat associative array with dot-notation keys
     */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !self::isIndexedArray($value)) {
                // Recursively flatten associative arrays
                $nested = self::flatten($value, $fullKey);
                $result = array_merge($result, $nested);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }
}
