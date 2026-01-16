<?php
namespace Zaplane\Traits;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

trait ActionResponseTrait
{
    /**
     * Standard port response
     */
    protected static function respond(array $data = [], string $port = 'main'): array
    {
        return [
            'port' => $port,
            'data' => $data,
            'meta' => [
                'timestamp' => time(),
                'node' => static::class,
            ],
        ];
    }

    /**
     * Success port (default main)
     */
    protected static function success(array $data = []): array
    {
        return static::respond($data, 'main');
    }

    /**
     * Error port
     */
    protected static function error(string $message, array $data = []): array
    {
        return static::respond(
            array_merge(['error' => $message], $data),
            'error'
        );
    }

    /**
     * Optional custom port
     */
    protected static function port(string $port, array $data = []): array
    {
        return static::respond($data, $port);
    }
}
