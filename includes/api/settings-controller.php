<?php
namespace Zaplane\API;

use WP_REST_Controller;
use WP_REST_Server;
use Zaplane\Framework\Classes\Container;
use Zaplane\Settings;
use Zaplane\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write the Zaplane admin settings (feature toggles + theme palettes).
 */
class SettingsController extends WP_REST_Controller {

	protected ?Container $container;

	public function __construct( ?Container $container = null ) {
		$this->container = $container;
		$this->namespace = 'zaplane/v1';
		$this->rest_base = 'settings';
	}

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		// The feature-filtered admin menu, so the SPA sidebar can refetch live
		// after a module is toggled (no full page reload).
		register_rest_route( $this->namespace, '/menu', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_menu' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );
	}

	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	public function get_settings() {
		return rest_ensure_response( Settings::get() );
	}

	public function update_settings( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}
		Settings::save( is_array( $body ) ? $body : [] );
		return rest_ensure_response( Settings::get() );
	}

	public function get_menu() {
		return rest_ensure_response( Helper::get_admin_menu_list() );
	}
}
