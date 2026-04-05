<?php
namespace Zaplane\Integrations\Woo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CustomerActionsTrait {

	private static function action_get_customers_all( array $config, array $input ): array {
		$pagination = self::get_pagination_args( $config );
		$result = self::query_customers([
			'limit' => $pagination['limit'],
			'page' => $pagination['page'],
		]);

		$items = array_map(function ( $customer ) {
			return self::build_customer_payload( $customer );
		}, $result['items']);
		return self::respond( [
			'count' => $result['total'],
			'items' => $items
		] );
	}

	private static function action_get_customer_single( array $config, array $input ): array {
		$customer_id = (int) ( $config['customer_id'] ?? 0 );
		if ( ! $customer_id ) {
			return self::error( 'Customer ID is required' );
		}
		$customer = new \WC_Customer( $customer_id );
		if ( ! $customer->get_id() ) {
			return self::error( 'Customer not found', [ 'customer_id' => $customer_id ] );
		}
		return self::respond([
			'customer' => self::build_customer_payload( $customer ),
		]);
	}

	private static function action_get_customer_by_email( array $config, array $input ): array {
		$email = $config['email'] ?? '';
		if ( '' === $email ) {
			return self::error( 'Email is required' );
		}
		$customer_id = self::get_customer_id_by_email( $email );
		if ( ! $customer_id ) {
			return self::error( 'Customer not found', [ 'email' => $email ] );
		}
		$customer = new \WC_Customer( $customer_id );
		if ( ! $customer->get_id() ) {
			return self::error( 'Customer not found', [ 'email' => $email ] );
		}
		return self::respond([
			'customer' => self::build_customer_payload( $customer ),
		]);
	}

	private static function action_create_customer( array $config, array $input ): array {
		$email = $config['email'] ?? '';
		if ( '' === $email ) {
			return self::error( 'Email is required' );
		}
		$username = $config['username'] ?? '';
		$password = $config['password'] ?? '';

		if ( function_exists( 'wc_create_new_customer' ) ) {
			$user_id = wc_create_new_customer( $email, $username, $password );
		} else {
			$user_id = wp_create_user( $username ? $username : $email, $password ? $password : wp_generate_password(), $email );
		}

		if ( is_wp_error( $user_id ) ) {
			return self::error( $user_id->get_error_message() );
		}

		$customer = new \WC_Customer( $user_id );
		if ( ! empty( $config['first_name'] ) ) {
			$customer->set_first_name( $config['first_name'] );
		}
		if ( ! empty( $config['last_name'] ) ) {
			$customer->set_last_name( $config['last_name'] );
		}
		$billing = self::parse_json_array( $config['billing'] ?? [] );
		if ( ! empty( $billing ) ) {
			$customer->set_billing( $billing );
		}
		$shipping = self::parse_json_array( $config['shipping'] ?? [] );
		if ( ! empty( $shipping ) ) {
			$customer->set_shipping( $shipping );
		}
		$customer->save();

		return self::respond([
			'customer' => self::build_customer_payload( $customer ),
		]);
	}
}
