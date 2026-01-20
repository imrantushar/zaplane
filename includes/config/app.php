<?php

if (!defined('ABSPATH')) exit;

return [
    'name' => 'Zaplane',
    'version' => ZAPLANE_VERSION,
    'slug' => ZAPLANE_PLUGIN_SLUG,
    'debug' => defined('WP_DEBUG') && WP_DEBUG,
    'url' => ZAPLANE_PLUGIN_ROOT_URI,
    'path' => ZAPLANE_ROOT_DIR_PATH,
];
