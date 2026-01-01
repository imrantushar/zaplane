<?php
/**
 * Plugin Name:     Zaplane
 * Plugin URI:      http://zaplane.pro
 * Description:     WordPress Automation plugin
 * Version:         1.1.0
 * Author:          kodezen
 * Author URI:      http://kodezen.com
 * License:         GPL-3.0+
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     zaplane
 * Domain Path:     /i18n/languages/
 *
 * Requires PHP: 7.4
 * Requires at least: 6.8
 * Tested up to: 6.8
 *
 * @package Zaplane
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Zaplane {
	private function __construct() {
		$this->define_constants();
		$this->set_global_settings();
		$this->load_dependency();
		$this->load_cli();
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		add_action( 'zaplane_loaded', [ $this, 'init_plugin' ] );
	}

	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
	public function define_constants() {
		/**
		 * Defines CONSTANTS for Whole plugins.
		 */
		define( 'ZAPLANE_VERSION', '1.1.0' );
		define( 'ZAPLANE_DB_VERSION', '1.0.0' );
		define( 'ZAPLANE_SETTINGS_NAME', 'zaplane_settings' );
		define( 'ZAPLANE_PLUGIN_FILE', __FILE__ );
		define( 'ZAPLANE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'ZAPLANE_PLUGIN_SLUG', 'zaplane' );
		define( 'ZAPLANE_PLUGIN_ROOT_URI', plugins_url( '/', __FILE__ ) );
		define( 'ZAPLANE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		define( 'ZAPLANE_APP_ROUTE_QUERY_VAR', 'zaplane_app_route' );
		define( 'ZAPLANE_INCLUDES_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'includes/' );
		define( 'ZAPLANE_TEMPLATES_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'templates/' );
		define( 'ZAPLANE_ASSETS_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'assets/' );
		define( 'ZAPLANE_ADDONS_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'addons/' );
		define( 'ZAPLANE_ASSETS_URI', ZAPLANE_PLUGIN_ROOT_URI . 'assets/' );
		define( 'ZAPLANE_TEMPLATE_DEBUG_MODE', false );
	}

	/**
	 * When WP has loaded all plugins, trigger the `zaplane_loaded` hook.
	 *
	 * This ensures `zaplane_loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		do_action( 'zaplane_loaded' );
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init_plugin() {
		// Init action.
		do_action( 'zaplane_before_init' );
		$this->dispatch_hooks();
		// Init action.
		do_action( 'zaplane_init' );
	}



	public function dispatch_hooks() {
		Zaplane\Migration::init();
		Zaplane\Assets::init();
		Zaplane\Admin::init();
		Zaplane\API::init();
		Zaplane\Automation::init();
		Zaplane\Ajax::init();
	}

	public function load_dependency() {
		require_once ZAPLANE_INCLUDES_DIR_PATH . 'autoload.php';
		require_once ZAPLANE_INCLUDES_DIR_PATH . 'dev-cli.php';
		require_once ZAPLANE_INCLUDES_DIR_PATH . 'functions.php';
	}

	public function load_cli() {
		if ( file_exists( ZAPLANE_ROOT_DIR_PATH . 'dev-cli.php' ) ) {
			require_once ZAPLANE_ROOT_DIR_PATH . 'dev-cli.php';
		}
	}

	public function set_global_settings() {
		$GLOBALS['zaplane_settings'] = json_decode( get_option( ZAPLANE_SETTINGS_NAME, '{}' ) );
	}

	

	public function activate() {
		Zaplane\Installer::init();
	}

}

/**
 * Initializes the main plugin
 *
 * @return \Zaplane
 */
function zaplane_start() {
	return Zaplane::init();
}

// Plugin Start
zaplane_start();