<?php

namespace Zaplane\Modules\AbandonedCart\API;

use WP_REST_Controller;
use WP_REST_Server;
use Zaplane\Modules\AbandonedCart\AbandonedCartHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsController extends WP_REST_Controller {

	protected $namespace = 'zaplane/v1';
	protected $rest_base = 'abandoned-cart/settings';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_settings( $request ) {
		$settings = AbandonedCartHelper::get_settings();

		return rest_ensure_response( [
			'settings'       => $settings,
			'gemcrm_tags'    => AbandonedCartHelper::get_gemcrm_tags(),
			'gemcrm_lists'   => AbandonedCartHelper::get_gemcrm_lists(),
			'wc_order_statuses' => $this->get_wc_order_statuses(),
			'user_roles'     => $this->get_user_roles(),
		] );
	}

	public function save_settings( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			$data = $request->get_params();
		}

		AbandonedCartHelper::save_settings( $data );

		return rest_ensure_response( [
			'success'  => true,
			'settings' => AbandonedCartHelper::get_settings(),
		] );
	}

	protected function get_wc_order_statuses(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return [];
		}
		$statuses = wc_get_order_statuses();
		$result   = [];
		foreach ( $statuses as $key => $label ) {
			$result[] = [
				'value' => str_replace( 'wc-', '', $key ),
				'label' => $label,
			];
		}
		return $result;
	}

	protected function get_user_roles(): array {
		global $wp_roles;
		if ( ! $wp_roles ) {
			return [];
		}
		$result = [];
		foreach ( $wp_roles->roles as $role_key => $role ) {
			$result[] = [
				'value' => $role_key,
				'label' => $role['name'],
			];
		}
		return $result;
	}
}
