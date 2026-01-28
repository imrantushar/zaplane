<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\ExternalAppIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Stripe extends ExternalAppIntegration {

	private const API_BASE_URL = 'https://api.stripe.com/v1';

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'stripe'; }
	public static function get_name(): string { return 'Stripe'; }
	public static function get_icon(): string { return 'stripe'; }

	/* ---------------------------------------------------------
	 * Authentication
	 * --------------------------------------------------------- */

	public static function get_auth_type(): string { return 'api_key'; }

	public static function get_auth_fields( ?string $auth_type = null ): array {
		return [
			'api_key' => [
				'type'     => 'password',
				'label'    => 'Secret Key',
				'required' => true,
				'help'     => 'Your Stripe Secret Key (sk_live_... or sk_test_...)',
			],
		];
	}

	protected static function build_auth_header( array $credentials ): ?string {
		$key = $credentials['api_key'] ?? '';
		return $key ? 'Bearer ' . $key : null;
	}

	public static function test_connection( array $credentials ): array {
		try {
			$result = self::stripe_request( 'GET', '/balance', [], $credentials );
			return [
				'success' => true,
				'message' => 'Connected to Stripe successfully',
				'details' => $result,
			];
		} catch ( \Exception $e ) {
			return [ 'success' => false, 'message' => $e->getMessage(), 'details' => [] ];
		}
	}

	/* ---------------------------------------------------------
	 * Triggers
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'payment_succeeded'    => [ 'label' => 'Payment Succeeded',      'hook' => 'stripe_webhook_payment_intent.succeeded' ],
			'payment_failed'       => [ 'label' => 'Payment Failed',         'hook' => 'stripe_webhook_payment_intent.payment_failed' ],
			'invoice_paid'         => [ 'label' => 'Invoice Paid',           'hook' => 'stripe_webhook_invoice.paid' ],
			'customer_created'     => [ 'label' => 'Customer Created',       'hook' => 'stripe_webhook_customer.created' ],
			'subscription_created' => [ 'label' => 'Subscription Created',   'hook' => 'stripe_webhook_customer.subscription.created' ],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$data = $args[0] ?? [];
		if ( empty( $data ) ) return false;

		return [
			'event_type' => $data['type'] ?? '',
			'object_id'  => $data['data']['object']['id'] ?? '',
			'amount'     => $data['data']['object']['amount'] ?? 0,
			'currency'   => $data['data']['object']['currency'] ?? '',
			'customer'   => $data['data']['object']['customer'] ?? '',
			'status'     => $data['data']['object']['status'] ?? '',
		];
	}

	/* ---------------------------------------------------------
	 * Actions
	 * --------------------------------------------------------- */

	public static function get_actions(): array {
		return [
			'create_charge'     => [ 'label' => 'Create Charge' ],
			'create_customer'   => [ 'label' => 'Create Customer' ],
			'retrieve_customer' => [ 'label' => 'Retrieve Customer' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$schemas = [
			'create_charge' => [
				[ 'key' => 'amount',      'label' => 'Amount (cents)',  'type' => 'expression', 'required' => true ],
				[ 'key' => 'currency',    'label' => 'Currency',       'type' => 'text', 'default' => 'usd' ],
				[ 'key' => 'customer',    'label' => 'Customer ID',    'type' => 'expression' ],
				[ 'key' => 'description', 'label' => 'Description',    'type' => 'textarea' ],
			],
			'create_customer' => [
				[ 'key' => 'email',       'label' => 'Email',       'type' => 'expression', 'required' => true ],
				[ 'key' => 'name',        'label' => 'Name',        'type' => 'expression' ],
				[ 'key' => 'description', 'label' => 'Description', 'type' => 'textarea' ],
			],
			'retrieve_customer' => [
				[ 'key' => 'customer_id', 'label' => 'Customer ID', 'type' => 'expression', 'required' => true ],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['config']['action'] ?? $node['data']['event'] ?? '';
		$config = $node['config']['data'] ?? $node['data']['config'] ?? [];
		$creds  = $node['_connection_credentials'] ?? [];

		if ( $action === 'create_charge' ) {
			$result = self::stripe_request( 'POST', '/payment_intents', [
				'amount'      => $config['amount'] ?? 0,
				'currency'    => $config['currency'] ?? 'usd',
				'customer'    => $config['customer'] ?? '',
				'description' => $config['description'] ?? '',
			], $creds );

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'charge_id' => $result['id'] ?? '',
				'status'    => $result['status'] ?? '',
			] ) ];
		}

		if ( $action === 'create_customer' ) {
			$result = self::stripe_request( 'POST', '/customers', [
				'email'       => $config['email'] ?? '',
				'name'        => $config['name'] ?? '',
				'description' => $config['description'] ?? '',
			], $creds );

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'customer_id' => $result['id'] ?? '',
			] ) ];
		}

		if ( $action === 'retrieve_customer' ) {
			$customer_id = $config['customer_id'] ?? '';
			if ( ! $customer_id ) throw new \Exception( 'Customer ID is required' );

			$result = self::stripe_request( 'GET', '/customers/' . $customer_id, [], $creds );

			return [ 'port' => 'main', 'data' => array_merge( $input, [
				'customer' => $result,
			] ) ];
		}

		return [ 'port' => 'main', 'data' => $input ];
	}

	public static function get_rate_limit(): int {
		return 100;
	}

	/* ---------------------------------------------------------
	 * Stripe API Helper
	 * --------------------------------------------------------- */

	private static function stripe_request( string $method, string $path, array $body, array $creds ): array {
		$url = self::API_BASE_URL . $path;
		$key = $creds['api_key'] ?? '';

		$args = [
			'method'  => $method,
			'headers' => [ 'Authorization' => 'Bearer ' . $key ],
			'timeout' => 30,
		];

		// Stripe uses form-encoded bodies, not JSON
		if ( ! empty( $body ) && $method !== 'GET' ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Stripe API request failed: ' . $response->get_error_message() );
		}

		$status  = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 400 ) {
			$msg = $decoded['error']['message'] ?? wp_remote_retrieve_body( $response );
			throw new \Exception( 'Stripe API error (' . $status . '): ' . $msg );
		}

		return is_array( $decoded ) ? $decoded : [];
	}
}
