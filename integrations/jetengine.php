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
		return 'jetengine.svg';
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
			'ID'                    => $post->ID ?? 0,
			'post_author'           => $post->post_author ?? null,
			'post_date'             => $post->post_date ?? null,
			'post_date_gmt'         => $post->post_date_gmt ?? null,
			'post_content'          => $post->post_content ?? null,
			'post_title'            => $post->post_title ?? null,
			'post_excerpt'          => $post->post_excerpt ?? null,
			'post_status'           => $post->post_status ?? null,
			'comment_status'        => $post->comment_status ?? null,
			'ping_status'           => $post->ping_status ?? null,
			'post_password'         => $post->post_password ?? null,
			'post_name'             => $post->post_name ?? null,
			'to_ping'               => $post->to_ping ?? null,
			'pinged'                => $post->pinged ?? null,
			'post_modified'         => $post->post_modified ?? null,
			'post_modified_gmt'     => $post->post_modified_gmt ?? null,
			'post_content_filtered' => $post->post_content_filtered ?? null,
			'post_parent'           => $post->post_parent ?? null,
			'guid'                  => $post->guid ?? null,
			'menu_order'            => $post->menu_order ?? null,
			'post_type'             => $post->post_type ?? null,
			'post_mime_type'        => $post->post_mime_type ?? null,
			'comment_count'         => $post->comment_count ?? null,
			'filter'                => 'raw',
			'meta_key'              => $meta_key,
			'meta_value'            => $meta_value,
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

				if ( $post ) {
					$data = self::resolve_payload( $post, $meta_key, $meta_value );
					$data['ID'] = (int) $post_id;
				} else {
					$data = [
						'ID'         => (int) $post_id,
						'meta_key'   => $meta_key,
						'meta_value' => $meta_value,
					];
				}

				return [
					'success'   => true,
					'timestamp' => current_time( 'mysql' ),
					'data'      => $data,
				];

		}//end switch

		return false;
	}

	public static function get_trigger_sample_output( string $event ): array {
		if ( 'post_type_field_update' !== $event ) {
			return [];
		}

		return [
			'success'   => true,
			'timestamp' => '2026-07-09 12:00:00',
			'data'      => [
				'ID'          => 101,
				'post_author' => '1',
				'post_date'   => '2026-07-09 12:00:00',
				'post_title'  => 'Sample Project',
				'post_status' => 'publish',
				'post_name'   => 'sample-project',
				'post_type'   => 'project',
				'guid'        => 'https://example.com/?post_type=project&p=101',
				'filter'      => 'raw',
				'meta_key'    => 'project_status',
				'meta_value'  => 'active',
			],
		];
	}

	public static function get_dynamic_queries(): array {
		return [
			'post_type' => [ self::class, 'post_type_query' ],
		];
	}

	public static function post_type_query( $query ) {
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
