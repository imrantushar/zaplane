<?php
namespace Zaplane\Core;

final class Container {
    private static array $services = [];

    public static function set(string $key, $service): void {
        self::$services[$key] = $service;
    }

    public static function get(string $key) {
        return self::$services[$key] ?? null;
    }
}
