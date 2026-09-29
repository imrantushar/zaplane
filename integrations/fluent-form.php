<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class FluentForm extends IntegrationBase {


	public static function get_slug(): string {
		return 'fluentform';
	}

	public static function get_name(): string {
		return 'Fluent Form';
	}

	public static function get_icon(): string {
		return 'fluentform.svg';
	}

	/** @inheritDoc */
	public static function get_docs_url(): array {
		return [
			'trigger' => 'https://zaplane.app/docs/fluent-forms/',
			'action'  => 'https://zaplane.app/docs/fluent-forms/',
		];
	}

	public static function get_triggers(): array {
		return [
			'submission_inserted' => [
				'label' => 'Form Submitted',
				'hook'  => 'fluentform/submission_inserted'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( in_array( $trigger, [ 'submission_inserted' ], true ) ) {
			return [
				[
					'key'      => 'form_id',
					'label'    => 'Form',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'fluentform',
						'query'       => 'form',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	private static function resolve_form_payload( $form ): array {
		return [
			'id'    => $form->id,
			'title' => $form->title,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'submission_inserted':
				$entryId  = $args[0] ?? null;
				$formData = $args[1] ?? null;
				$form     = $args[2] ?? null;

				if ( ! $entryId || ! $form ) {
					return false;
				}

					$config       = $node['data']['config'] ?? [];
					$requiredForm = $config['form_id'] ?? 'any';

				if ( 'any' !== $requiredForm && (int) $requiredForm !== (int) $form->id ) {
					return false;
				}

				return [
					'success' => true,
					'entry_id'  => $entryId,
					'form_data' => $formData,
					'form' => self::resolve_form_payload( $form ),
				];
		}//end switch
		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'form' => [ self::class, 'query_forms' ],
		];
	}

	public static function query_forms() {

		$options = [
			[
				'label' => 'Any Form',
				'name'  => 'any'
			],
		];
		if ( function_exists( 'wpFluent' ) ) {
			$forms = wpFluent()
				->table( 'fluentform_forms' )
				->select( [ 'id', 'title' ] )
				->get();

			if ( $forms ) {
				foreach ( $forms as $form ) {
					$options[] = [
						'label' => $form->title,
						'name'  => $form->id,
					];
				}
			}
		}

		return $options;
	}

	public static function get_output_ports(): array {
		return [
			'main' => 'Main output',
		];
	}
}
