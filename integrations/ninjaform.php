<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Ninjaform extends IntegrationBase {


	public static function get_slug(): string {
		return 'ninjaform';
	}

	public static function get_icon(): string {
		return 'ninjaform';
	}	

	public static function get_triggers(): array {
		return [
			'process_ninja_form' => [
				'label' => 'Form Submit',
				'hook'  => 'ninja_forms_after_submission'
			],

		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'process_ninja_form' !== $trigger ) {
			return [];
		}

		return [
			[
				'key'      => 'form_id',
				'label'    => 'Form',
				'type'     => 'select',
				'dynamic' => [
					'integration' => 'ninjaform',
					'query'       => 'forms',
					'select'      => [ 'name', 'label' ],
				],
				'required' => true,
			],
		];
	}

	private static function resolve_form_payload( $form ): array {
		if ( ! $form ) {
			return [];
		}

		$id    = method_exists( $form, 'get_id' ) ? (int) $form->get_id() : 0;
		$title = method_exists( $form, 'get_setting' ) ? (string) $form->get_setting( 'title' ) : '';

		return [
			'id'    => $id,
			'title' => $title,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'process_ninja_form':
				$formData = $args[0] ?? null;
				if ( empty( $formData ) || ! is_array( $formData ) ) {
					return false;
				}

				$currentFormId = $formData['form_id']
					?? $formData['id']
					?? ( $formData['form']['id'] ?? null )
					?? null;

				if ( empty( $currentFormId ) ) {
					return false;
				}

				$config       = $node['data']['config'] ?? [];
				$requiredForm = $config['form_id'] ?? 'any';

				if ( 'any' !== $requiredForm && (int) $requiredForm !== (int) $currentFormId ) {
					return false;
				}

				$form = null;
				if ( function_exists( 'Ninja_Forms' ) ) {
					$form = Ninja_Forms()->form( (int) $currentFormId );
				}

				$entryId =
					$formData['sub_id']
					?? ( $formData['extra']['sub_id'] ?? null )
					?? ( $formData['submission']['id'] ?? null )
					?? null;

				return [
					'success'   => true,
					'entry_id'  => $entryId,
					'form_data' => $formData,
					'form'      => $form ? self::resolve_form_payload( $form ) : null,
				];
		}//end switch
		return false;
	}

	public static function get_actions(): array {
		return [];
	}

	public static function get_action_config_schema( string $action ): array {

		$schemas = [];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		return [
			'port' => 'main',
			'data' => $input
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'forms' => [ self::class, 'query_forms' ],
		];
	}

	public static function query_forms() {

		$options = [
			[
				'label' => 'Any Form',
				'name' => 'any',
			],
		];

		if ( function_exists( 'Ninja_Forms' ) ) {
			$forms = Ninja_Forms()->form()->get_forms();

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[] = [
						'label' => $form->get_setting( 'title' ),
						'name' => $form->get_id(),
					];
				}
			}
		}

		return $options;
	}


}
