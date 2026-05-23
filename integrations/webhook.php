<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

class Webhook extends IntegrationBase {


	public static function get_slug(): string {
		return 'webhook';
	}

	public static function get_name(): string {
		return 'Webhook';
	}

	public static function get_icon(): string {
		return 'webhook.svg';
	}
	public static function supports_webhook(): bool {
		return true;
	}

	/**
	 * Path-as-secret model: the workflow-specific path embedded in the
	 * URL is the credential. Anything that reaches this code already
	 * matched a registered workflow path. We additionally reject
	 * obviously-wrong request shapes so half-broken probes don't
	 * accidentally fire a trigger.
	 *
	 * A real HMAC scheme can layer on top via the
	 * `zaplane_webhook_verify_signature` filter — return false to
	 * reject, true to accept, null/no-op to fall back to the default.
	 */
	public static function verify_webhook_signature( \WP_REST_Request $request ): bool {
		$override = apply_filters( 'zaplane_webhook_verify_signature', null, $request );
		if ( true === $override ) {
			return true;
		}
		if ( false === $override ) {
			return false;
		}
		$method = strtoupper( (string) $request->get_method() );
		return in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true );
	}

	public static function get_triggers(): array {
		return [
			'incoming' => [
				'label' => 'Incoming Webhook',
				'hook'  => 'rest_api_init', // fires via REST route, not a WP action; this is documentation
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return [
			[
				'key' => 'path',
				'label' => 'Webhook Path',
				'type' => 'text',
				'required' => true,
				'help' => 'Example: order-created'
			]
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		return $args[0] ?? [];
	}
}
