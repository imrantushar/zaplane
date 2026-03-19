<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Bricks extends IntegrationBase {


	public static function get_slug(): string {
		return 'bricks';
	}

	public static function get_name(): string {
		return 'Bricks';
	}

	public static function get_icon(): string {
		return 'bricksb-builder.svg';
	}

	public static function get_triggers(): array {
		return [
			'bricks_form_submit' => [
				'label' => 'Bricks Form Submitted',
				'hook'  => 'bricks/form/custom_action'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'bricks_form_submit' ], true ) ) {
			return [
				[
					'key'      => 'form_action',
					'label'    => 'Form action',
					'type'     => 'text',
					'required' => true,
				],
			];
		}
		return [];
	}

	private static function resolve_form_payload( $form ): array {
		return [
			'uploaded_files' => $form->get_uploaded_files(),
			'form_fields'    => $form->get_fields(),
			'form_settings'  => $form->get_settings(),
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'bricks_form_submit':
				$form = $args[0] ?? null;
				if ( ! $form ) {
					return false;
				}

					$config = $node['data']['config'] ?? [];
					$required_action = $config['form_action'] ?? '';

					$settings = $form->get_settings();
					$actual_action = $settings['actions']['custom_action'] ?? '';

				if ( $required_action && $required_action !== $actual_action ) {
					return false;
				}

				return [
					'success' => true,
					'form' => self::resolve_form_payload( $form ),
				];
		}//end switch
		return false;
	}

	public static function get_actions(): array {
		return [];
	}

	public static function get_action_config_schema( string $action ): array {
		return [];
	}

	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input
		];
	}
}
