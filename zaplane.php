<?php
/**
 * Plugin Name:     Zaplane
 * Plugin URI:      http://zaplane.pro
 * Description:     WordPress Automation Plugin
 * Version:         0.0.1
 * Author:          kodezen
 * Author URI:      http://kodezen.com
 * License:         GPL-3.0+
 * Text Domain:     zaplane
 *
 * Requires PHP: 7.4
 * Tested up to: 6.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define('ZAPLANE_VERSION', '0.0.1');
define('ZAPLANE_ALLOW_LOGS', true);
define('ZAPLANE_PLUGIN_SLUG', 'zaplane');
define('ZAPLANE_PLUGIN_FILE', __FILE__);
define('ZAPLANE_PLUGIN_ROOT_URI', plugins_url( '/', __FILE__ ) );
define('ZAPLANE_ROOT_DIR_PATH', plugin_dir_path(__FILE__));
define('ZAPLANE_INCLUDES_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'includes/');
define('ZAPLANE_INTEGRATION_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'integration/');
define( 'ZAPLANE_ASSETS_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'assets/' );
define( 'ZAPLANE_ASSETS_URI', ZAPLANE_PLUGIN_ROOT_URI . 'assets/' );

// Include the main bootstrap class
require_once __DIR__ . '/includes/framework/core/zaplane.php';

// Development Purpose CLI Command
if(file_exists(__DIR__ . '/dev-cli.php')){
    require_once __DIR__ . '/dev-cli.php';
}

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    add_action('plugins_loaded', function () {
        \Zaplane\Framework\Console\Kernel::getInstance()->boot();
    });
}

// Activation hook
register_activation_hook(ZAPLANE_PLUGIN_FILE, function () {
    \Zaplane\Installer::init()->run();
});
