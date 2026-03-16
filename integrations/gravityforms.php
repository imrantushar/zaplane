<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;
use GFFormsModel;

class Gravityforms extends IntegrationBase {


	public static function get_slug(): string {
		return 'gravityforms';
	}

	public static function get_name(): string {
		return 'Gravity Forms';
	}

	public static function get_icon(): string {
		return 'gravity-forms-logo.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'gform_after_submission'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submitted' === $trigger ) {

			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'gravityforms',
						'query'       => 'form_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	protected static function convertkey( array $entry ): array {
		$result = [];
		foreach ( $entry as $key => $value ) {
			$new_key = str_replace( '.', ':', $key );
			$result[ $new_key ] = $value;
		}
		return $result;
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$entry = $args[0] ?? null;
				$form  = $args[1] ?? null;

				if ( ! $entry || ! $form ) {
					return false;
				}

				$form_id = $form['id'] ?? 0;

				if ( ! empty( $node['form_id'] ) && 'any' !== $node['form_id'] ) {
					if ( (int) $form_id !== (int) $node['form_id'] ) {
						return false;
					}
				}

				$enter_entry = self::convertkey( $entry );
				$enter_entry['title'] = $form['title'] ?? '';

				return [
					'success'  => true,
					'form_id'  => $form_id,
					'entry_id' => $entry['id'] ?? 0,
					'data'     => $enter_entry,
				];
		}//end switch
		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'form_query' => [ self::class, 'form_query_types' ],
		];
	}

	public static function form_query_types( $q ) {
		$options = [
			[
				'label' => 'Any Form',
				'name' => 'any'
			],
		];

		if ( class_exists( 'GFFormsModel' ) && is_callable( [ 'GFFormsModel', 'get_forms' ] ) ) {
			$forms = GFFormsModel::get_forms( true );
			foreach ( $forms as $form ) {
				$options[] = [
					'name' => $form->id,
					'label' => $form->title ?? $form->post_title ?? '',
				];
			}
		}

		return $options;
	}
}
