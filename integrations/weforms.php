<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Weforms extends IntegrationBase {

	public static function get_slug(): string {
		return 'weforms';
	}

	public static function get_name(): string {
		return 'weForms';
	}

	public static function get_icon(): string {
		return 'weforms.svg';
	}

	public static function get_triggers(): array {
		return [
			'weforms_entry_submission' => [
				'label' => 'Form Submission',
				'hook'  => 'weforms_entry_submission',
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {

		if ( 'weforms_entry_submission' === $trigger ) {

			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'dynamic'  => [
						'integration' => 'weforms',
						'query'       => 'form_query',
						'select'      => [ 'value', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {

		if ( ( $node['event'] ?? '' ) !== 'weforms_entry_submission' ) {
			return false;
		}

		// Modern call shape: payload is the first arg (array with form_id,
		// entry_id, field labels). Use it directly when present.
		$first = $args[0] ?? null;
		if ( is_array( $first ) && isset( $first['form_id'] ) ) {
			$form_id    = (int) $first['form_id'];
			$configured = $node['data']['config']['form_id'] ?? ( $node['config']['form_id'] ?? 'any' );
			if ( 'any' !== $configured && (int) $configured !== $form_id ) {
				return false;
			}
			return $first;
		}

		// Legacy: (entry_id, form_id) — relies on the weForms API for fields.
		switch ( $node['event'] ) {

			case 'weforms_entry_submission':
				$entry_id = $args[0] ?? 0;
				$form_id  = $args[1] ?? 0;

				if ( empty( $entry_id ) || empty( $form_id ) ) {
					return false;
				}

				if ( ! function_exists( 'weforms_get_entry_data' ) ) {
					return false;
				}

				$data_all = weforms_get_entry_data( $entry_id );

				if ( empty( $data_all['data'] ) ) {
					return false;
				}

				$submitted_data = $data_all['data'];

				if ( ! empty( $data_all['fields'] ) ) {

					foreach ( $data_all['fields'] as $key => $field ) {

						if (
							isset( $field['type'] ) &&
							in_array( $field['type'], [ 'image_upload', 'file_upload' ], true )
						) {

							if ( ! empty( $submitted_data[ $key ] ) ) {

								$file = explode( '"', $submitted_data[ $key ] );

								$submitted_data[ $key ] = $file[1] ?? $submitted_data[ $key ];
							}
						}
					}
				}

				$name_organized = [];

				foreach ( $submitted_data as $key => $value ) {

					if ( preg_match( '/name/i', $key ) ) {

						unset( $submitted_data[ $key ] );

						$name_values = explode( '|', $value );

						if ( count( $name_values ) === 2 ) {

							$name_organized = [
								'first_name' => $name_values[0] ?? '',
								'last_name'  => $name_values[1] ?? '',
							];

						} elseif ( count( $name_values ) >= 3 ) {

							$name_organized = [
								'first_name'  => $name_values[0] ?? '',
								'middle_name' => $name_values[1] ?? '',
								'last_name'   => $name_values[2] ?? '',
							];
						}
					}//end if
				}//end foreach

				$final_data = array_merge(
					$submitted_data,
					$name_organized,
					[
						'form_id'  => $form_id,
						'entry_id' => $entry_id,
					]
				);

				$selected_form = $node['config']['form_id'] ?? 'any';

				if (
					'any' !== $selected_form &&
					(string) $selected_form !== (string) $form_id
				) {
					return false;
				}

				return $final_data;
		}//end switch

		return false;
	}

	public static function get_dynamic_queries(): array {

		return [
			'form_query' => [ self::class, 'form_query_types' ],
		];
	}

	public static function form_query_types( $query ) {

		$options = [
			[
				'label' => 'Any Form',
				'value' => 'any',
			],
		];

		if ( function_exists( 'weforms' ) ) {

			$forms = weforms()->form->all();

			if ( ! empty( $forms['forms'] ) ) {

				foreach ( $forms['forms'] as $form ) {

					$options[] = [
						'value' => $form->id,
						'label' => $form->name,
					];
				}
			}
		}

		return $options;
	}
}
