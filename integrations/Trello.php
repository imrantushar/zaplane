<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

class Trello extends IntegrationBase {


	public static function get_slug(): string {
		return 'trello';
	}

	public static function get_triggers(): array {
		return [
			'card_created' => [
				'label' => 'Card Created',
				'hook' => 'trello_webhook'
			]
		];
	}

	public static function get_actions(): array {
		return [ 'create_card' => [ 'label' => 'Create Card' ] ];
	}

	public static function resolve_trigger( array $node, array $args ) {
		return [ 'card' => $args[0] ?? [] ];
	}

	public static function execute_node( array $node, array $input ): array {
		if ( ( $node['config']['action'] ?? '' ) === 'create_card' ) {
		}
		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
