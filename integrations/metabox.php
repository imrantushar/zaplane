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

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {
			case 'form_submission':
				$object = $args[0] ?? null;

				if ( ! $object || ! isset( $object->post_id ) ) {
					return false;
				}

				$post_id       = $object->post_id;
				$config        = $object->config ?? [];
				$form_id       = $config['id'] ?? null;
				$selected_form = $node['config']['form_id'] ?? 'any';

				if ( 'any' !== $selected_form && $form_id ) {
					if ( (string) $selected_form !== (string) $form_id ) {
						return false;
					}
				}

				$all_meta     = get_post_meta( $post_id );
				$field_values = [];

				foreach ( $all_meta as $key => $val ) {

					if ( strpos( $key, '_' ) === 0 ) {
						continue;
					}

					if ( ! is_array( $val ) || ! isset( $val[0] ) ) {
						continue;
					}

					$value = maybe_unserialize( $val[0] );
					$field_values[ $key ] = $value;
				}

				$post      = get_post( $post_id );
				$post_data = $post ? get_object_vars( $post ) : [];
				unset( $post_data['ID'] );
				$data = array_merge( [ 'id' => $form_id ], $field_values, [ 'post_id' => $post_id ], $post_data );

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
