<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Classes\IntegrationBase;

class Jetengine extends IntegrationBase {

	public static function get_slug(): string {
		return 'jetengine';
	}

	public static function get_name(): string {
		return 'JetEngine';
	}

	public static function get_icon(): string {
		return 'jetengine-icon.svg';
	}

	public static function get_triggers(): array {
		return [
			'post_type_field_update' => [
				'label' => 'Updated JetEngine field on post type',
				'hook'  => 'updated_post_meta'
			],
		];
	}

	public static function get_trigger_config_schema( string $trigger ): array {
		if ( 'post_type_field_update' === $trigger ) {
			return [
				[
					'key'      => 'post_type',
					'label'    => 'Post Type',
					'type'     => 'select',
					'dynamic' => [
						'integration' => 'jetengine',
						'query'       => 'post_type',
						'select'      => [ 'name', 'label' ],
					],
					'required' => true,
				],
			];
		}

		return [];
	}

	private static function resolve_payload( $post, $meta_key, $meta_value ) {
		return [
			'ID'                   => $post->ID,
			'post_author'          => $post->post_author,
			'post_date'            => $post->post_date,
			'post_date_gmt'        => $post->post_date_gmt,
			'post_content'         => $post->post_content,
			'post_title'           => $post->post_title,
			'post_excerpt'         => $post->post_excerpt,
			'post_status'          => $post->post_status,
			'comment_status'       => $post->comment_status,
			'ping_status'          => $post->ping_status,
			'post_password'        => $post->post_password,
			'post_name'            => $post->post_name,
			'to_ping'              => $post->to_ping,
			'pinged'               => $post->pinged,
			'post_modified'        => $post->post_modified,
			'post_modified_gmt'    => $post->post_modified_gmt,
			'post_content_filtered' => $post->post_content_filtered,
			'post_parent'          => $post->post_parent,
			'guid'                 => $post->guid,
			'menu_order'           => $post->menu_order,
			'post_type'            => $post->post_type,
			'post_mime_type'       => $post->post_mime_type,
			'comment_count'        => $post->comment_count,
			'filter'               => 'raw',
			'meta_key'             => $meta_key,
			'meta_value'           => $meta_value,
		];
	}

	public static function resolve_trigger( array $node, array $args ) {

		switch ( $node['event'] ) {

			case 'post_type_field_update':
				$meta_id    = $args[0] ?? null;
				$post_id    = $args[1] ?? null;
				$meta_key   = $args[2] ?? null;
				$meta_value = maybe_unserialize( $args[3] ?? null );

				if ( ! $post_id || ! $meta_key ) {
					return false;
				}

				if (
					wp_is_post_autosave( $post_id ) ||
					wp_is_post_revision( $post_id ) ||
					( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				) {
					return false;
				}

				if ( '_edit_last' === $meta_key ) {
					return false;
				}

				static $processed = [];
				$unique_key = $post_id . '|' . $meta_key;
				if ( isset( $processed[ $unique_key ] ) ) {
					return false;
				}
				$processed[ $unique_key ] = true;

				$post = get_post( $post_id );
				if ( ! $post ) {
					return false;
				}

				$config_post_type = $node['data']['config']['post_type'] ?? 'any';
				if ( 'any' !== $config_post_type && $config_post_type !== $post->post_type ) {
					return false;
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data'      => self::resolve_payload( $post, $meta_key, $meta_value ),
				];

		}//end switch

		return false;
	}

	public static function get_dynamic_queries(): array {
		return [
			'post_type' => [ self::class, 'post_type_query' ],
		];
	}

	public static function post_type_query( $q ) {
		$types = get_post_types( [ 'public' => true ], 'objects' );
		$all_list = [
			[
				'label' => 'Any Post Type',
				'name' => 'any'
			],
		];

		foreach ( $types as $type ) {
			$all_list[] = [
				'label' => $type->label,
				'name'  => $type->name,
			];
		}

		return $all_list;
	}
}
