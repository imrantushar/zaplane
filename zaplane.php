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

use Zaplane\Framework\Classes\ConnectionManager;
use Zaplane\Framework\Classes\Container;
use Zaplane\Framework\Classes\OAuthHandler;
use Zaplane\Framework\Core\Automation;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Core\ModuleManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Zaplane {
	private static ?self $instance = null;
	public Container $container;

	private function __construct() {
		$this->define_constants();
		$this->set_global_settings();
		$this->load_dependencies();
		$this->load_cli();

		$this->container = $this->boot_container();

		register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );

		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		add_action( 'zaplane_loaded', [ $this, 'init_plugin' ] );
	}

	public static function init(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function set_global_settings(): void {
		$GLOBALS['zaplane_settings'] = json_decode( get_option( 'zaplane_settings', '{}' ) );
	}

	public function define_constants(): void {
		define( 'ZAPLANE_VERSION', '0.0.1' );
		define( 'ZAPLANE_ALLOW_LOGS', true );
		define( 'ZAPLANE_PLUGIN_SLUG', 'zaplane' );
		define( 'ZAPLANE_PLUGIN_FILE', __FILE__ );
		define( 'ZAPLANE_PLUGIN_ROOT_URI', plugins_url( '/', __FILE__ ) );
		define( 'ZAPLANE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		define( 'ZAPLANE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'ZAPLANE_INCLUDES_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'includes/' );
		define( 'ZAPLANE_FRAMEWORK_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'includes/framework/' );
		define( 'ZAPLANE_INTEGRATION_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'integration/' );
		define( 'ZAPLANE_ASSETS_DIR_PATH', ZAPLANE_ROOT_DIR_PATH . 'assets/' );
		define( 'ZAPLANE_ASSETS_URI', ZAPLANE_PLUGIN_ROOT_URI . 'assets/' );
	}

	private function load_dependencies(): void {
		require_once ZAPLANE_INCLUDES_DIR_PATH . 'autoload.php';
		require_once ZAPLANE_FRAMEWORK_DIR_PATH . 'functions.php';
		require_once ZAPLANE_INCLUDES_DIR_PATH . 'utils/functions.php';
		require_once ZAPLANE_INCLUDES_DIR_PATH . 'utils/helper.php';
	}

	public function load_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( file_exists( ZAPLANE_FRAMEWORK_DIR_PATH . 'console/kernel.php' ) ) {
				\Zaplane\Framework\Console\Kernel::getInstance()->boot();
			}
		}
	}

	private function boot_container(): Container {
		$container = new Container();
		// Integration loader
		$container->set( 'integrations', fn( $c) => IntegrationLoader::init( $c ) );
		// Optional core modules
		$container->set( 'modules', fn( $c) => ModuleManager::init( $c ) );
		$container->set( 'automation', fn( $c) => Automation::init( $c ) );
		// Connection services
		$container->set( 'connections', fn( $c) => new ConnectionManager() );
		$container->set( 'oauth', fn( $c) => new OAuthHandler( $c->get( 'connections' ) ) );
		return $container;
	}

	public function activate_plugin() {
		\Zaplane\Installer::init()->run();
	}

	public function on_plugins_loaded(): void {
		do_action( 'zaplane_loaded' );
	}

	public function init_plugin(): void {
		// Initialize modules first
		$modules = $this->container->get( 'modules' );
		$modules->boot();

		$automation = $this->container->get( 'automation' );
		$automation->boot();

		do_action( 'zaplane_init' );
	}

	public function deactivate_plugin(): void {}
}

// Bootstrap plugin
Zaplane::init();
