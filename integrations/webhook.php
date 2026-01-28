<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Webhook extends IntegrationBase {

	/* ---------------------------------------------------------
	 * Identity
	 * --------------------------------------------------------- */

	public static function get_slug(): string { return 'webhook'; }
	public static function get_name(): string { return 'Webhook'; }
	public static function get_icon(): string { return 'webhook'; }
	public static function get_category(): string { return 'tool'; }

	public static function supports_webhook(): bool {
		return true;
	}

	/* ---------------------------------------------------------
	 * Triggers
	 * --------------------------------------------------------- */

	public static function get_triggers(): array {
		return [
			'incoming' => [ 'label' => 'Incoming Webhook', 'hook' => 'zaplane_webhook_incoming' ],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		return [
			[
				'key'      => 'path',
				'label'    => 'Webhook Path',
				'type'     => 'text',
				'required' => true,
				'help'     => 'Example: order-created',
			],
		];
	}

	public static function resolve_trigger( array $node, array $args ) {
		$data = $args[0] ?? [];
		if ( empty( $data ) ) return false;

		return is_array( $data ) ? $data : [ 'payload' => $data ];
	}
}
