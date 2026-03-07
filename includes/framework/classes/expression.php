<?php
namespace Zaplane\Framework\Classes;

if (!defined('ABSPATH')) exit;

class Expression {

    /**
     * Evaluate a Zaplane expression
     */
    public static function evaluate($expr, array $data) {

        if ($expr === null || $expr === '') {
            return null;
        }

        // If not expression, return raw
        if (!str_contains($expr, '{{')) {
            return $expr;
        }

        return preg_replace_callback('/{{(.*?)}}/', function($m) use ($data) {
            return self::compute(trim($m[1]), $data);
        }, $expr);
    }

    /**
     * Compute inside {{ }}
     */
    private static function compute(string $code, array $data) {

        // Replace dot syntax with PHP array access
        // user.email → $data["user"]["email"]
        $php = preg_replace_callback('/[a-zA-Z0-9_][a-zA-Z0-9_.]*/', function($m) use ($data) {

            $key = $m[0];

            // allow true, false, null, numbers
            if (in_array($key, ['true','false','null']) || is_numeric($key)) {
                return $key;
            }

            // Convert foo.bar.baz → $data["foo"]["bar"]["baz"]
            $parts = explode('.', $key);
            $php = '$data';

            foreach ($parts as $p) {
                $php .= '["'.$p.'"]';
            }

            return $php;

        }, $code);

        try {
            // Evaluate safely
            return eval("return {$php};");
        } catch (\Throwable $e) {
            return null;
        }
    }
}
