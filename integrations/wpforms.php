<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Wpforms extends IntegrationBase {


	public static function get_slug(): string {
		return 'wpforms';
	}

	public static function get_name(): string {
		return 'WPForms';
	}

	public static function get_icon(): string {
		return '';
	}

	public static function get_triggers(): array {
		return [
			'form_submitted' => [
				'label' => 'Form Submitted',
				'hook'  => 'wpforms_process_complete'
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
						'integration' => 'wpforms',
						'query'       => 'form_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	private static function uploadfield( array $files ) {
		$all_files = [];

		foreach ( $files as $file ) {
			$all_files[] = $file['value'] ?? null;
		}

		return $all_files;
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submitted':
				$fields     = $args[0] ?? [];
				$entry      = $args[1] ?? [];
				$form_data  = $args[2] ?? [];
				$entry_id = $entry['id'] ?? 0;

				if ( empty( $form_data['id'] ) ) {
					return false;
				}

				if ( ! empty( $node['form_id'] ) && 'any' !== $node['form_id'] ) {
					if ( (int) $form_data['id'] !== (int) $node['form_id'] ) {
						return false;
					}
				}

				$data = [];

				if ( ! empty( $entry['post_id'] ) ) {
					$data['post_id'] = $entry['post_id'];
				}

				foreach ( $fields as $field_id => $field ) {
					if ( ! is_array( $field ) ) {
						continue;
					}

					if ( ( $field['type'] ?? '' ) === 'name' ) {
						$data[ $field_id ]            = $field['value'] ?? '';
						$data[ $field_id . ':first' ]  = $field['first'] ?? '';
						$data[ $field_id . ':last' ]   = $field['last'] ?? '';
						$data[ $field_id . ':middle' ] = $field['middle'] ?? '';
					} elseif ( ( $field['type'] ?? '' ) === 'file-upload' ) {
						$data[ $field_id ] = self::uploadfield(
							$field['value_raw'] ?? []
						);
					} else {
						$data[ $field_id ] = $field['value'] ?? '';
					}
				}

				return [
					'success'   => true,
					'form_id'   => $form_data['id'],
					'entry_id'  => $entry_id,
					'data'      => $data,
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
				'label' => 'Any From',
				'name' => 'any'
			],
		];

		if ( function_exists( 'WPForms' ) ) {
			$forms = WPForms()->form->get();
			foreach ( $forms as $form ) {
				$options[]  = [
					'label' => $form->post_title,
					'name' => $form->ID,
				];
			}
		}

		return $options;
	}
}
