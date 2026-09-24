<?php
fwrite(STDERR,"START_BOOTSTRAP\n");
define('WP_USE_THEMES', false);
$GLOBALS['wp_filter']['pre_option_active_plugins'][10][] = [
 'function' => static function(){ return [ 'zencommunity/zencommunity.php', 'zencommunity-pro/zencommunity-pro.php', 'zaplane/zaplane.php' ]; },
 'accepted_args' => 1,
];
$GLOBALS['wp_filter']['plugin_loaded'][10][] = [ 'function' => static function($p){ fwrite(STDERR, 'PLUGIN_LOADED=' . $p . PHP_EOL); }, 'accepted_args' => 1 ];
$GLOBALS['wp_filter']['plugins_loaded'][10][] = [ 'function' => static function(){ fwrite(STDERR, 'PLUGINS_LOADED' . PHP_EOL); }, 'accepted_args' => 0 ];
$GLOBALS['wp_filter']['init'][10][] = [ 'function' => static function(){ fwrite(STDERR, 'INIT_CALLED' . PHP_EOL); }, 'accepted_args' => 0 ];
require dirname(__DIR__, 5) . '/wp-load.php';
fwrite(STDERR,"SHORTINIT_OK\n");
echo 'DB=' . (isset($GLOBALS['wpdb']) && $GLOBALS['wpdb']->check_connection(false) ? 'OK' : 'FAIL') . PHP_EOL;
