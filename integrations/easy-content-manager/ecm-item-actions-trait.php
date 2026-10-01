<?php

namespace Zaplane\Integrations\EasyContentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EcmItemActionsTrait {

	protected static function ecm_execute_item_action( string $event, array $config ): ?array {
		switch ( $event ) {
			case 'create_item':
				return static::ecm_action_create_item( $config );

			case 'update_item':
				return static::ecm_action_update_item( $config );

			case 'delete_item':
				return static::ecm_action_delete_item( $config );

			case 'get_item':
				return static::ecm_action_get_item( $config );

			case 'find_item':
				return static::ecm_action_find_item( $config );

			case 'change_item_status':
				return static::ecm_action_change_status( $config, (string) ( $config['status'] ?? '' ) );

			case 'publish_item':
				return static::ecm_action_change_status( $config, 'publish' );

			case 'duplicate_item':
				return static::ecm_action_duplicate_item( $config );

			case 'update_custom_field_value':
				return static::ecm_action_update_custom_field( $config );

			case 'get_custom_field_value':
				return static::ecm_action_get_custom_field( $config );

			case 'update_multiple_custom_fields':
				return static::ecm_action_update_multiple_custom_fields( $config );
		}//end switch

		return null;
	}

	private static function ecm_action_create_item( array $config ): array {
		$post_type = sanitize_key( (string) ( $config['post_type'] ?? '' ) );

		if ( '' === $post_type ) {
			return static::ecm_action_error( 'A post type is required to create an item.' );
		}

		$title = (string) ( $config['post_title'] ?? '' );

		if ( '' === trim( $title ) ) {
			return static::ecm_action_error( 'An item title is required.' );
		}

		$postarr = [
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_content' => (string) ( $config['post_content'] ?? '' ),
			'post_excerpt' => (string) ( $config['post_excerpt'] ?? '' ),
			'post_status'  => sanitize_key( (string) ( $config['post_status'] ?? 'draft' ) ),
		];

		$author_id = absint( $config['author_id'] ?? 0 );
		if ( $author_id ) {
			$postarr['post_author'] = $author_id;
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return static::ecm_action_error( $post_id->get_error_message() );
		}

		if ( ! $post_id ) {
			return static::ecm_action_error( 'The item could not be created.' );
		}

		// Build the payload from what we asked for, not from a re-read: a
		// just-inserted post may not be readable back within the same run.
		return static::ecm_success(
			array_merge(
				static::ecm_item_payload( (int) $post_id ),
				[
					'item_id'     => (int) $post_id,
					'post_id'     => (int) $post_id,
					'title'       => $postarr['post_title'],
					'slug'        => sanitize_title( $postarr['post_title'] ),
					'status'      => $postarr['post_status'],
					'post_type'   => $postarr['post_type'],
					'content'     => $postarr['post_content'],
					'excerpt'     => $postarr['post_excerpt'],
					'author_id'   => (int) ( $postarr['post_author'] ?? 0 ),
				]
			)
		);
	}

	private static function ecm_action_update_item( array $config ): array {
		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		$postarr = [ 'ID' => $post_id ];

		foreach ( [ 'post_title', 'post_content', 'post_excerpt' ] as $field ) {
			$value = (string) ( $config[ $field ] ?? '' );
			if ( '' !== $value ) {
				$postarr[ $field ] = $value;
			}
		}

		$status = (string) ( $config['post_status'] ?? '' );
		if ( '' !== $status ) {
			$postarr['post_status'] = sanitize_key( $status );
		}

		$result = wp_update_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			array_merge( static::ecm_item_payload( $post_id ), [ 'success' => true ] )
		);
	}

	private static function ecm_action_delete_item( array $config ): array {
		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		$force = in_array( (string) ( $config['force_delete'] ?? 'no' ), [ 'yes', 'true', '1', 1, true ], true );

		$deleted = $force ? wp_delete_post( $post_id, true ) : wp_trash_post( $post_id );

		if ( ! $deleted ) {
			return static::ecm_action_error( 'The item could not be deleted.' );
		}

		return static::ecm_success(
			[
				'success' => true,
				'item_id' => $post_id,
				'deleted' => $force ? 'permanently' : 'trashed',
			]
		);
	}

	private static function ecm_action_get_item( array $config ): array {
		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		return static::ecm_success( static::ecm_item_payload( $post_id ) );
	}

	private static function ecm_action_find_item( array $config ): array {
		$post_type = sanitize_key( (string) ( $config['post_type'] ?? '' ) );
		$value     = (string) ( $config['search_value'] ?? '' );

		if ( '' === $post_type ) {
			return static::ecm_action_error( 'A post type is required.' );
		}

		$args = [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => min( 100, max( 1, absint( $config['limit'] ?? 10 ) ) ),
		];

		if ( 'meta' === (string) ( $config['search_field'] ?? 'title' ) ) {
			$args['meta_query'] = [
				[
					'key'   => (string) ( $config['meta_key'] ?? '' ),
					'value' => $value,
				],
			];
		} else {
			$args['s'] = $value;
		}

		$posts = get_posts( $args );
		$items = [];

		foreach ( (array) $posts as $post ) {
			$id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : absint( $post );

			if ( $id ) {
				$items[] = static::ecm_item_payload( $id );
			}
		}

		return static::ecm_success(
			[
				'items' => $items,
				'found' => count( $items ),
			]
		);
	}

	private static function ecm_action_change_status( array $config, string $status ): array {
		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( '' === $status ) {
			return static::ecm_action_error( 'A status is required.' );
		}

		$result = wp_update_post( [
			'ID' => $post_id,
			'post_status' => sanitize_key( $status )
		], true );

		if ( is_wp_error( $result ) ) {
			return static::ecm_action_error( $result->get_error_message() );
		}

		return static::ecm_success(
			array_merge( static::ecm_item_payload( $post_id ), [ 'success' => true ] )
		);
	}

	private static function ecm_action_duplicate_item( array $config ): array {
		$post_id = absint( $config['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		$status = sanitize_key( (string) ( $config['new_status'] ?? 'draft' ) );

		$new_id = wp_insert_post(
			[
				'post_type'    => (string) ( $post->post_type ?? 'post' ),
				'post_title'   => (string) ( $post->post_title ?? '' ),
				'post_content' => (string) ( $post->post_content ?? '' ),
				'post_excerpt' => (string) ( $post->post_excerpt ?? '' ),
				'post_status'  => '' !== $status ? $status : 'draft',
			],
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return static::ecm_action_error( 'The item could not be duplicated.' );
		}

		// Copy all meta except ECM's own counters and the duplicate's identity.
		$exclude = array_merge(
			static::ECM_INTERNAL_META_KEYS,
			[ '_ecm_upvoted_users', '_ecm_reactions', '_zaplane_ecm_frontend_submission' ]
		);

		foreach ( (array) get_post_meta( $post_id ) as $meta_key => $values ) {
			if ( in_array( $meta_key, $exclude, true ) || 0 === strpos( $meta_key, '_' ) ) {
				continue;
			}

			foreach ( (array) $values as $meta_value ) {
				add_post_meta( (int) $new_id, $meta_key, static::ecm_maybe_unserialize( $meta_value ) );
			}
		}

		return static::ecm_success(
			array_merge(
				static::ecm_item_payload( (int) $new_id ),
				[
					'item_id'   => (int) $new_id,
					'post_id'   => (int) $new_id,
					'title'     => (string) ( $post->post_title ?? '' ),
					'content'   => (string) ( $post->post_content ?? '' ),
					'excerpt'   => (string) ( $post->post_excerpt ?? '' ),
					'post_type' => (string) ( $post->post_type ?? '' ),
					'status'    => '' !== $status ? $status : 'draft',
				]
			)
		);
	}

	private static function ecm_action_update_custom_field( array $config ): array {
		$post_id  = absint( $config['post_id'] ?? 0 );
		$meta_key = (string) ( $config['meta_key'] ?? '' );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( '' === $meta_key || 0 === strpos( $meta_key, '_' ) ) {
			return static::ecm_action_error( 'A valid field name is required.' );
		}

		$value = $config['value'] ?? '';

		update_post_meta( $post_id, $meta_key, $value );

		return static::ecm_success(
			[
				'success'  => true,
				'item_id'  => $post_id,
				'meta_key' => $meta_key,
				'value'    => $value,
			]
		);
	}

	private static function ecm_action_get_custom_field( array $config ): array {
		$post_id  = absint( $config['post_id'] ?? 0 );
		$meta_key = (string) ( $config['meta_key'] ?? '' );

		if ( ! $post_id ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		if ( '' === $meta_key ) {
			return static::ecm_action_error( 'A field name is required.' );
		}

		return static::ecm_success(
			[
				'item_id'  => $post_id,
				'meta_key' => $meta_key,
				'value'    => static::ecm_maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) ),
			]
		);
	}

	private static function ecm_action_update_multiple_custom_fields( array $config ): array {
		$post_id = absint( $config['post_id'] ?? 0 );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return static::ecm_action_error( 'Item not found.' );
		}

		$fields = static::ecm_key_value_pairs( $config['fields'] ?? [] );

		if ( empty( $fields ) ) {
			return static::ecm_action_error( 'No fields were provided.' );
		}

		$updated = [];

		foreach ( $fields as $meta_key => $value ) {
			if ( '' === $meta_key || 0 === strpos( $meta_key, '_' ) ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
			$updated[ $meta_key ] = $value;
		}

		if ( empty( $updated ) ) {
			return static::ecm_action_error( 'No valid field names were provided.' );
		}

		return static::ecm_success(
			[
				'success' => true,
				'item_id' => $post_id,
				'updated' => $updated,
			]
		);
	}

	/**
	 * Normalize a `map` field (rows of key/value) or an associative array /
	 * JSON object string into a plain map.
	 *
	 * @return array<string,mixed>
	 */
	protected static function ecm_key_value_pairs( $fields ): array {
		if ( is_string( $fields ) ) {
			$decoded = json_decode( $fields, true );
			$fields  = is_array( $decoded ) ? $decoded : [];
		}

		if ( ! is_array( $fields ) ) {
			return [];
		}

		$pairs = [];

		foreach ( $fields as $row ) {
			if ( is_array( $row ) && array_key_exists( 'key', $row ) ) {
				$pairs[ (string) $row['key'] ] = $row['value'] ?? '';
				continue;
			}

			if ( is_scalar( $row ) ) {
				continue;
			}

			// Associative entry.
			$keys = array_keys( (array) $row );
			if ( 1 === count( $keys ) ) {
				$pairs[ (string) $keys[0] ] = $row[ $keys[0] ];
			}
		}

		return $pairs;
	}

	/**
	 * Normalize a comma-separated string or array of term names/IDs.
	 *
	 * @return array<int,int|string>
	 */
	protected static function ecm_term_list( $terms ): array {
		if ( is_string( $terms ) ) {
			$terms = array_map( 'trim', explode( ',', $terms ) );
		}

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$list = [];

		foreach ( $terms as $term ) {
			$term = trim( (string) $term );

			if ( '' === $term ) {
				continue;
			}

			$list[] = ctype_digit( $term ) ? absint( $term ) : $term;
		}

		return $list;
	}

	protected static function ecm_action_error( string $message ): array {
		return static::ecm_success(
			[
				'success' => false,
				'error'   => $message,
			]
		);
	}

	protected static function ecm_success( array $data ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	protected static function ecm_unknown_action( string $event ): array {
		return static::ecm_success( [ 'error' => sprintf( 'Unknown Easy Content Manager action: %s', $event ) ] );
	}
}
