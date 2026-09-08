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

		// The module registry, so the Modules screen renders from one definition
		// instead of a second copy kept in JavaScript.
		register_rest_route( $this->namespace, '/modules', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_modules' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( $this->namespace, '/teasers', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_teasers' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		// One-click activation from wherever a teaser appears, so nobody has to
		// abandon a half-built workflow to go and flip a switch.
		register_rest_route( $this->namespace, '/modules/(?P<key>[a-z0-9_]+)/activate', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'activate_module' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( $this->namespace, '/teasers/(?P<key>[a-z0-9_]+)/dismiss', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'dismiss_teaser' ],
			'permission_callback' => [ $this, 'permissions_check' ],
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

	public function get_modules() {
		return rest_ensure_response( array_values( Settings::modules() ) );
	}

	public function get_teasers( $request ) {
		$app = (string) ( $request->get_param( 'app' ) ?? '' );
		if ( '' !== $app ) {
			return rest_ensure_response( \Zaplane\Features\Teasers::for_app( $app ) );
		}

		$screen = (string) ( $request->get_param( 'screen' ) ?? '' );
		if ( '' !== $screen ) {
			return rest_ensure_response( \Zaplane\Features\Teasers::for_screen( $screen ) );
		}

		return rest_ensure_response( \Zaplane\Features\Teasers::all_visible() );
	}

	public function activate_module( $request ) {
		$key = (string) $request['key'];

		if ( ! isset( Settings::modules()[ $key ] ) ) {
			return new \WP_Error( 'unknown_module', 'Unknown module: ' . $key, [ 'status' => 404 ] );
		}

		Settings::save( [ 'features' => [ $key => true ] ] );

		return rest_ensure_response( [
			'activated' => true,
			'key'       => $key,
			'features'  => Settings::get()['features'],
		] );
	}

	public function dismiss_teaser( $request ) {
		$key = (string) $request['key'];

		return rest_ensure_response( [
			'dismissed' => \Zaplane\Features\Teasers::dismiss( $key ),
			'key'       => $key,
		] );
	}
}
