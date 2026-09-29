<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Gameengine extends IntegrationBase {


    public static function get_slug(): string {
        return 'gameengine';
    }

    public static function get_name(): string {
        return 'GameEngine';
    }

    public static function get_icon(): string {
        return 'gameengine.svg';
    }

    public static function get_triggers(): array {
		return [
			'location_created' => [
				'label' => 'Location Created',
				'hook'  => 'wpforms_process_complete'
			],
		];
	}

    public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'location_created' === $trigger ) {
			return [

			];
		}//end if
		return [];
	}

    public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'location_created':
		}//end switch
		return false;
	}

	public static function get_trigger_sample_output( string $trigger ): array {

		return [];
	}

	public static function get_actions(): array
	{
		return [
			'location_created' => ['label' => 'Location Create'],
		];
	}

	public static function get_action_config_schema( string $action ): array {

		$schemas = [

		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {

		$config = $node['data']['config'] ?? [];
		$event = $node['data']['event'] ?? '';

		$method = 'action_' . $event;

		if ( method_exists( static::class, $method ) ) {
			return static::$method( $config, $input );
		}

		return [
			'port' => 'main',
			'data' => $input
		];
	}

	private static function action_location_created( array $config, array $input ): array {

	}

	public static function get_dynamic_queries(): array {
		return [
			'query' => [ self::class, 'form_query_types' ],
		];
	}

	public static function form_query_types( $query ) {

	}
}
