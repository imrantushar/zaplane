<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;
use Zaplane\Traits\ActionResponseTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdvanceCustomFields extends IntegrationBase {

	use ActionResponseTrait;

	public static function get_slug(): string {
		return 'advancecustomfields';
	}

	public static function get_name(): string {
		return 'Advanced Custom Fields';
	}

	public static function get_icon(): string {
		return 'acf.svg';
	}

	public static function get_triggers(): array {
		return [
			'acf_save_post' => [
				'label' => 'ACF Fields Saved',
				'hook'  => 'acf/save_post',
			],
			'acf_post_field_updated' => [
				'label' => 'ACF Post Field Value Updated',
				'hook'  => 'updated_post_meta',
			],
			'acf_user_field_updated' => [
				'label' => 'ACF User Field Value Updated',
				'hook'  => [ 'updated_user_meta', 'added_user_meta' ],
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'acf_save_post' === $trigger ) {
			return [
				[
					'key'      => 'post_id',
					'label'    => 'Post ID (leave empty for any)',
					'type'     => 'text',
					'required' => false,
				],
			];
		}

		if ( 'acf_post_field_updated' === $trigger ) {
			return [
				[
					'key'      => 'field_name',
					'label'    => 'Field Name (leave empty for any)',
					'type'     => 'text',
					'required' => false,
				],
			];
		}

		if ( 'acf_user_field_updated' === $trigger ) {
			return [
				[
					'key'      => 'field_name',
					'label'    => 'Field Name (leave empty for any)',
					'type'     => 'text',
					'required' => false,
				],
			];
		}

		return [];
	}

	public static function resolve_trigger( array $node, array $args ) {
		switch ( $node['event'] ) {
			case 'acf_save_post':
				$post_id = $args[0] ?? 0;
				if ( ! $post_id ) {
					return false;
				}

				$config        = $node['data']['config'] ?? [];
				$required_post = $config['post_id'] ?? '';
				if ( $required_post && (int) $required_post !== (int) $post_id ) {
					return false;
				}

				$fields = function_exists( 'get_fields' ) ? ( get_fields( $post_id ) ?: [] ) : [];

				return [
					'post_id'  => $post_id,
					'fields'   => $fields,
					'saved_at' => current_time( 'mysql' ),
				];

			case 'acf_post_field_updated':
				// updated_post_meta: meta_id, object_id, meta_key, meta_value
				$post_id    = $args[1] ?? 0;
				$meta_key   = $args[2] ?? '';
				$meta_value = $args[3] ?? '';

				if ( ! $post_id || ! $meta_key ) {
					return false;
				}

				// Only handle ACF-managed keys (skip internal _ prefixed keys)
				if ( str_starts_with( $meta_key, '_' ) ) {
					return false;
				}

				$config         = $node['data']['config'] ?? [];
				$required_field = $config['field_name'] ?? '';
				if ( $required_field && $required_field !== $meta_key ) {
					return false;
				}

				return [
					'post_id'    => $post_id,
					'field_name' => $meta_key,
					'value'      => $meta_value,
					'updated_at' => current_time( 'mysql' ),
				];

			case 'acf_user_field_updated':
				// updated_user_meta / added_user_meta: meta_id, user_id, meta_key, meta_value
				$user_id    = $args[1] ?? 0;
				$meta_key   = $args[2] ?? '';
				$meta_value = $args[3] ?? '';

				if ( ! $user_id || ! $meta_key ) {
					return false;
				}

				if ( str_starts_with( $meta_key, '_' ) ) {
					return false;
				}

				$config         = $node['data']['config'] ?? [];
				$required_field = $config['field_name'] ?? '';
				if ( $required_field && $required_field !== $meta_key ) {
					return false;
				}

				return [
					'user_id'    => $user_id,
					'field_name' => $meta_key,
					'value'      => $meta_value,
					'updated_at' => current_time( 'mysql' ),
				];
		}

		return false;
	}

	public static function get_trigger_sample_output( string $event ): array {
		$timestamp = '2024-01-01 12:00:00';

		$acf_fields = [
			'headline'     => 'Welcome to our site',
			'subtitle'     => 'The best place to learn',
			'cta_link'     => 'https://example.com/signup',
			'is_featured'  => true,
		];

		$post = [
			'post_id' => 42,
		];

		$user = [
			'user_id' => 1,
		];

		$field = [
			'field_name' => 'headline',
			'value'      => 'Welcome to our site',
		];

		$samples = [
			'acf_save_post' => array_merge(
				$post,
				[
					'fields'   => $acf_fields,
					'saved_at' => $timestamp,
				]
			),
			'acf_post_field_updated' => array_merge(
				$post,
				$field,
				[ 'updated_at' => $timestamp ]
			),
			'acf_user_field_updated' => array_merge(
				$user,
				$field,
				[ 'updated_at' => $timestamp ]
			),
		];

		if ( isset( $samples[ $event ] ) ) {
			return $samples[ $event ];
		}

		// Prefix / keyword fallbacks so no trigger returns [].
		if ( false !== strpos( $event, 'user' ) ) {
			return $samples['acf_user_field_updated'];
		}
		if ( false !== strpos( $event, 'save' ) ) {
			return $samples['acf_save_post'];
		}
		if ( false !== strpos( $event, 'field' ) || false !== strpos( $event, 'post' ) ) {
			return $samples['acf_post_field_updated'];
		}

		// Catch-all: always non-empty.
		return array_merge( $post, $field, [ 'updated_at' => $timestamp ] );
	}

	public static function get_actions(): array {
		return [
			'get_post_field'            => [ 'label' => 'Get Post Custom Field Value' ],
			'get_user_field'            => [ 'label' => 'Get User Custom Field Value' ],
			'get_options_field'         => [ 'label' => 'Get Options Page Field Value' ],
			'update_post_field'         => [ 'label' => 'Update Post Custom Field Value' ],
			'update_user_field'         => [ 'label' => 'Update User Custom Field Value' ],
			'update_options_field'      => [ 'label' => 'Update Options Page Field Value' ],
			'update_repeater_field'     => [ 'label' => 'Update Repeater Field Value' ],
			'update_group_field'        => [ 'label' => 'Update Group Field Value' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$field_name = [
			'key'      => 'field_name',
			'label'    => 'Field Name',
			'type'     => 'text',
			'required' => true,
		];
		$value = [
			'key'      => 'value',
			'label'    => 'Value',
			'type'     => 'expression',
			'required' => true,
		];
		$post_id = [
			'key'      => 'post_id',
			'label'    => 'Post ID',
			'type'     => 'expression',
			'required' => true,
		];
		$user_id = [
			'key'      => 'user_id',
			'label'    => 'User ID',
			'type'     => 'expression',
			'required' => true,
		];

		$schemas = [
			'get_post_field'   => [ $post_id, $field_name ],
			'update_post_field' => [ $post_id, $field_name, $value ],

			'get_user_field'   => [ $user_id, $field_name ],
			'update_user_field' => [ $user_id, $field_name, $value ],

			'get_options_field'    => [ $field_name ],
			'update_options_field' => [ $field_name, $value ],

			'update_repeater_field' => [
				$post_id,
				$field_name,
				[
					'key'      => 'rows',
					'label'    => 'Rows (JSON array of objects)',
					'type'     => 'expression',
					'required' => true,
				],
			],

			'update_group_field' => [
				$post_id,
				$field_name,
				[
					'key'      => 'sub_fields',
					'label'    => 'Sub Fields (JSON object)',
					'type'     => 'expression',
					'required' => true,
				],
			],
		];

		return $schemas[ $action ] ?? [];
	}

	public static function execute_node( array $node, array $input ): array {
		$config = $node['data']['config'] ?? [];
		$event  = $node['data']['event'] ?? '';

		switch ( $event ) {
			case 'get_post_field':
				$post_id    = $config['post_id'] ?? 0;
				$field_name = $config['field_name'] ?? '';
				$value      = function_exists( 'get_field' ) ? get_field( $field_name, $post_id ) : null;
				return static::success( [
					'post_id'    => $post_id,
					'field_name' => $field_name,
					'value'      => $value,
				] );

			case 'get_user_field':
				$user_id    = $config['user_id'] ?? 0;
				$field_name = $config['field_name'] ?? '';
				$value      = function_exists( 'get_field' ) ? get_field( $field_name, 'user_' . $user_id ) : null;
				return static::success( [
					'user_id'    => $user_id,
					'field_name' => $field_name,
					'value'      => $value,
				] );

			case 'get_options_field':
				$field_name = $config['field_name'] ?? '';
				$value      = function_exists( 'get_field' ) ? get_field( $field_name, 'option' ) : null;
				return static::success( [
					'field_name' => $field_name,
					'value'      => $value,
				] );

			case 'update_post_field':
				$post_id    = $config['post_id'] ?? 0;
				$field_name = $config['field_name'] ?? '';
				$value      = $config['value'] ?? '';
				if ( function_exists( 'update_field' ) ) {
					update_field( $field_name, $value, $post_id );
				}
				return static::success( [
					'post_id'    => $post_id,
					'field_name' => $field_name,
					'value'      => $value,
				] );

			case 'update_user_field':
				$user_id    = $config['user_id'] ?? 0;
				$field_name = $config['field_name'] ?? '';
				$value      = $config['value'] ?? '';
				if ( function_exists( 'update_field' ) ) {
					update_field( $field_name, $value, 'user_' . $user_id );
				}
				return static::success( [
					'user_id'    => $user_id,
					'field_name' => $field_name,
					'value'      => $value,
				] );

			case 'update_options_field':
				$field_name = $config['field_name'] ?? '';
				$value      = $config['value'] ?? '';
				if ( function_exists( 'update_field' ) ) {
					update_field( $field_name, $value, 'option' );
				}
				return static::success( [
					'field_name' => $field_name,
					'value'      => $value,
				] );

			case 'update_repeater_field':
				$post_id    = $config['post_id'] ?? 0;
				$field_name = $config['field_name'] ?? '';
				$rows       = $config['rows'] ?? [];
				if ( is_string( $rows ) ) {
					$rows = json_decode( $rows, true ) ?: [];
				}
				if ( function_exists( 'update_field' ) ) {
					update_field( $field_name, $rows, $post_id );
				}
				return static::success( [
					'post_id'    => $post_id,
					'field_name' => $field_name,
					'rows'       => $rows,
				] );

			case 'update_group_field':
				$post_id    = $config['post_id'] ?? 0;
				$field_name = $config['field_name'] ?? '';
				$sub_fields = $config['sub_fields'] ?? [];
				if ( is_string( $sub_fields ) ) {
					$sub_fields = json_decode( $sub_fields, true ) ?: [];
				}
				if ( function_exists( 'update_field' ) ) {
					update_field( $field_name, $sub_fields, $post_id );
				}
				return static::success( [
					'post_id'    => $post_id,
					'field_name' => $field_name,
					'sub_fields' => $sub_fields,
				] );
		}

		return [ 'port' => 'main', 'data' => $input ];
	}
}
