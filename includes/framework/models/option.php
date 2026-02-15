<?php

namespace Zaplane\Framework\Models;

if (!defined('ABSPATH')) exit;

class Option extends WpModel
{
    protected static string $table = 'options';
    protected static string $primaryKey = 'option_id';
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'option_name',
        'option_value',
        'autoload',
    ];

    protected static array $casts = [
        'option_id' => 'integer',
    ];

    public function getValue()
    {
        $value = $this->option_value;
        $unserialized = @unserialize($value);
        return $unserialized !== false ? $unserialized : $value;
    }

    public static function get(string $name, $default = null)
    {
        $option = static::where('option_name', $name)->first();
        if (!$option) {
            return $default;
        }
        return $option->getValue();
    }

    public static function set(string $name, $value, string $autoload = 'yes'): bool
    {
        $serialized = is_array($value) || is_object($value) ? serialize($value) : $value;

        $existing = static::where('option_name', $name)->first();

        if ($existing) {
            $existing->option_value = $serialized;
            return $existing->save();
        }

        return (bool) static::create([
            'option_name' => $name,
            'option_value' => $serialized,
            'autoload' => $autoload,
        ]);
    }

    public static function remove(string $name): bool
    {
        $option = static::where('option_name', $name)->first();
        if (!$option) {
            return false;
        }
        return $option->delete();
    }
}
