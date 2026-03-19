<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

class Stripe extends IntegrationBase {


	public static function get_slug(): string {
		return 'stripe';
	}

	public static function get_icon(): string {
		return 'stripe.svg';
	}

	public static function get_triggers(): array {
		return [
			'payment_succeeded' => [
				'label' => 'Payment Succeeded',
				'hook' => 'stripe_webhook'
			]
		];
	}

	public static function get_actions(): array {
		return [ 'charge_customer' => [ 'label' => 'Charge Customer' ] ];
	}

	public static function resolve_trigger( array $node, array $args ) {
		return [ 'charge' => $args[0] ?? [] ];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ( $node['config']['action'] ?? '' ) === 'charge_customer' ) {
			return [];
		}
		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
