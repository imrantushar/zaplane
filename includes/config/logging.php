<?php

if (!defined('ABSPATH')) exit;

return [
    'default' => 'file',

    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => ZAPLANE_ROOT_DIR_PATH . 'logs/zaplane.log',
            'level' => 'debug',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'zaplane_logs',
            'level' => 'info',
        ],

        'error_log' => [
            'driver' => 'error_log',
            'level' => 'error',
        ],

        'memory' => [
            'driver' => 'memory',
            'level' => 'debug',
        ],
    ],
];
