<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Metabox extends IntegrationBase {


	public static function get_slug(): string {
		return 'metabox';
	}

	public static function get_name(): string {
		return 'MetaBox';
	}

	public static function get_icon(): string {
		return 'metabox.svg';
	}

	public static function get_triggers(): array {
		return [
			'form_submission' => [
				'label' => 'Form Submission',
				'hook'  => 'rwmb_frontend_after_save_post'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'form_submission' === $trigger ) {
			return [
				[
					'key'      => 'form_id',
					'label'    => 'Forms',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'metabox',
						'query'       => 'form_query',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}//end if
		return [];
	}

	private static function extract_form_fields( $form_id ) {
		if ( ! function_exists( 'rwmb_meta' ) ) {
			return [];
		}

		$meta_box = rwmb_get_registry( 'meta_box' );
		$form     = $meta_box->get( $form_id );

		if ( ! $form || ! isset( $form->meta_box['fields'] ) ) {
			return [];
		}

		$upload_file  = [ 'file_upload', 'single_image', 'file' ];
		$field_detail = $form->meta_box['fields'];
		$fields       = [];

		foreach ( $field_detail as $field ) {

			if ( ! empty( $field['id'] ) && 'submit' !== $field['type'] ) {
				$fields[] = [
					'name'  => $field['id'],
					'type'  => in_array( $field['type'], $upload_file, true ) ? 'file' : $field['type'],
					'label' => $field['name'] ?? '',
				];
			}
		}

		return $fields;
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submission':
				$post_id = $args[0] ?? null;
				$config  = $args[1] ?? [];

				if ( ! $post_id || empty( $config['id'] ) ) {
					return false;
				}

				$form_id        = $config['id'];
				$select_form_id = $node['config']['form_id'] ?? 'any';

				if ( 'any' !== $select_form_id && $select_form_id !== $form_id ) {
					return false;
				}

				$fields      = self::extract_form_fields( $form_id );
				$field_value = [];

				foreach ( $fields as $field ) {
					$value = rwmb_meta( $field['name'], [], $post_id );

					if ( ! $value ) {
						continue;
					}

					if ( 'file' === $field['type'] ) {

						if ( isset( $value['path'] ) ) {
							$field_value[ $field['name'] ] = $value['path'];
						} elseif ( is_array( $value ) ) {
							$field_value[ $field['name'] ] = array_map( fn( $f ) => $f['path'] ?? null, $value );
						}
					} else {
						$field_value[ $field['name'] ] = $value;
					}
				}

				$post             = get_post( $post_id );
				$post_field_value = $post ? get_object_vars( $post ) : [];
				unset( $post_field_value['ID'] );

				$all_meta    = get_post_meta( $post_id );
				$meta_values = [];

				foreach ( $all_meta as $key => $val ) {
					$meta_values[ $key ] = maybe_unserialize( $val[0] );
				}

				$data = array_merge(
					[ 'id' => $form_id ],
					$field_value,
					$meta_values,
					[ 'post_id' => $post_id ],
					$post_field_value
				);

				return [
					'success' => true,
					'data'    => $data,
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
		if ( ! function_exists( 'rwmb_meta' ) || ! function_exists( 'mb_frontend_submission_load' ) ) {
			return [
				[
					'label' => 'MetaBox is not installed or activated',
					'name'  => '',
				],
			];
		}

		$meta_box = rwmb_get_registry( 'meta_box' );
		$forms    = array_values( $meta_box->all() );
		$options  = [];

		foreach ( $forms as $form ) {

			if ( isset( $form->meta_box['id'], $form->meta_box['title'] ) ) {
				$options[] = [
					'name'  => $form->meta_box['id'],
					'label' => $form->meta_box['title'],
				];
			}
		}

		array_unshift(
			$options,
			[
				'label' => 'Any Form',
				'name'  => 'any',
			]
		);

		return $options;
	}
}
