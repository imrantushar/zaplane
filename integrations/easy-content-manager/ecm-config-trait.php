<?php

namespace Zaplane\Integrations\EasyContentManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait EcmConfigTrait {

	/**
	 * Post-meta keys ECM manages internally. Custom-field triggers must ignore
	 * these plus anything with an underscore prefix.
	 */
	protected const ECM_INTERNAL_META_KEYS = [
		'ecm_bookmark_users',
		'ecm_upvote_count',
		'ecm_view_count',
		'ecm_average_rating',
		'ecm_review_count',
		'ecm_rating_distribution',
	];

	/**
	 * User-meta keys ECM manages internally, excluded from the user-meta trigger.
	 */
	protected const ECM_INTERNAL_USER_META_KEYS = [
		'ecm_user_bookmarks',
		'_ecm_upvoted_posts',
		'_ecm_user_reactions',
	];

	/**
	 * The comment type the Review & Ratings addon stores reviews as.
	 */
	protected static function ecm_review_type(): string {
		return \defined( 'ECM_REVIEWS_TYPE' ) ? (string) constant( 'ECM_REVIEWS_TYPE' ) : 'ecm_review';
	}

	/**
	 * The table the Claim addon stores claims in.
	 */
	protected static function ecm_claims_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ecm_claims';
	}

	/**
	 * Whether the Easy Content Manager plugin (free or premium) is active.
	 */
	protected static function ecm_active(): bool {
		return \defined( 'EASY_CONTENT_MANAGER_VERSION' );
	}

	/**
	 * Decoded easy_content_manager_addons_settings — a JSON map of addon slug => bool.
	 */
	protected static function ecm_addons_settings(): array {
		static $cache = null;

		if ( null === $cache ) {
			$cache = (array) json_decode( (string) get_option( 'easy_content_manager_addons_settings', '{}' ), true );
		}

		return $cache;
	}

	/**
	 * Whether an ECM addon is switched on (by its slug, e.g. 'bookmark', 'claim').
	 */
	protected static function ecm_addon_enabled( string $slug ): bool {
		if ( ! static::ecm_active() ) {
			return false;
		}

		$settings = static::ecm_addons_settings();

		return ! empty( $settings[ $slug ] );
	}

	/**
	 * Whether an addon is available for a specific post type, mirroring ECM's
	 * Helper::is_addon_enabled_for_post_type(): the addon settings live under
	 * "{addon}_addon" in easy_content_manager_settings with a shortcode_settings
	 * object; without a specific-post-types list every post type qualifies.
	 */
	protected static function ecm_addon_for_post_type( string $addon, string $post_type ): bool {
		if ( ! static::ecm_addon_enabled( $addon ) ) {
			return false;
		}

		$settings = json_decode( (string) get_option( 'easy_content_manager_settings', '{}' ) );
		$key      = str_replace( '-', '_', $addon ) . '_addon';

		$addon_settings = isset( $settings->{$key} ) ? $settings->{$key} : null;
		$shortcode      = isset( $addon_settings->shortcode_settings ) ? (array) $addon_settings->shortcode_settings : [];

		if ( empty( $shortcode['enable_specific_post_types'] ) ) {
			return true;
		}

		return ! empty( $post_type ) && in_array( $post_type, (array) ( $shortcode['specific_post_types'] ?? [] ), true );
	}

	/**
	 * ECM-created post types as slug => label.
	 *
	 * @return array<string,string>
	 */
	protected static function ecm_post_type_slugs(): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = [];

		if ( ! static::ecm_active() ) {
			return $cache;
		}

		foreach ( static::ecm_config_posts( 'ecm_post_types' ) as $post ) {
			$config = static::ecm_decode_config( $post );
			$slug   = (string) ( $config['post_slug'] ?? $config['post_type'] ?? '' );

			if ( '' === $slug ) {
				continue;
			}

			$cache[ $slug ] = (string) ( $config['labels']['title'] ?? $config['label'] ?? $slug );
		}

		return $cache;
	}

	/**
	 * ECM-created taxonomies as slug => label.
	 *
	 * @return array<string,string>
	 */
	protected static function ecm_taxonomy_slugs(): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = [];

		if ( ! static::ecm_active() ) {
			return $cache;
		}

		foreach ( static::ecm_config_posts( 'ecm_taxonomy' ) as $post ) {
			$config = static::ecm_decode_config( $post );
			$slug   = (string) ( $config['taxonomy_slug'] ?? '' );

			if ( '' === $slug ) {
				continue;
			}

			$cache[ $slug ] = (string) ( $config['labels']['title'] ?? $config['label'] ?? $slug );
		}

		return $cache;
	}

	/**
	 * Meta keys of ECM-defined custom fields, optionally scoped to one post type.
	 * Fields live both embedded in post-type configs and in field groups (whose
	 * resolved locations are mirrored to the ecm_field_group_location meta).
	 *
	 * @return array<int,string>
	 */
	protected static function ecm_field_meta_keys( string $post_type = '' ): array {
		static $cache = [];

		$cache_key = '' === $post_type ? '__all__' : $post_type;
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$keys = [];

		if ( static::ecm_active() ) {
			foreach ( static::ecm_config_posts( 'ecm_post_types' ) as $post ) {
				$config = static::ecm_decode_config( $post );
				$slug   = (string) ( $config['post_slug'] ?? $config['post_type'] ?? '' );

				if ( '' !== $post_type && '' !== $slug && $slug !== $post_type ) {
					continue;
				}

				$keys = array_merge( $keys, static::ecm_field_keys_from_config( $config ) );
			}

			foreach ( static::ecm_config_posts( 'ecm_field_group' ) as $post ) {
				if ( '' !== $post_type ) {
					$locations = static::ecm_maybe_unserialize( get_post_meta( $post->ID, 'ecm_field_group_location', true ) );
					$locations = is_array( $locations ) ? $locations : [];

					if ( ! in_array( $post_type, $locations, true ) ) {
						continue;
					}
				}

				$keys = array_merge( $keys, static::ecm_field_keys_from_config( static::ecm_decode_config( $post ) ) );
			}
		}//end if

		$keys = array_values( array_unique( array_filter( $keys ) ) );
		$keys = array_values(
			array_diff(
				$keys,
				static::ECM_INTERNAL_META_KEYS,
				[ '_ecm_upvoted_users', '_ecm_reactions' ]
			)
		);

		$cache[ $cache_key ] = $keys;

		return $keys;
	}

	/**
	 * Meta keys of ECM custom fields scoped to user profiles (field groups
	 * located on current_user / current_user_role).
	 *
	 * @return array<int,string>
	 */
	protected static function ecm_user_meta_keys(): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$cache = [];

		if ( ! static::ecm_active() ) {
			return $cache;
		}

		foreach ( static::ecm_config_posts( 'ecm_field_group' ) as $post ) {
			$locations = static::ecm_maybe_unserialize( get_post_meta( $post->ID, 'ecm_field_group_location', true ) );
			$locations = is_array( $locations ) ? $locations : [];

			if ( empty( $locations['current_user'] ) && empty( $locations['current_user_role'] ) ) {
				continue;
			}

			$cache = array_merge( $cache, static::ecm_field_keys_from_config( static::ecm_decode_config( $post ) ) );
		}

		$cache = array_values( array_unique( array_filter( array_diff( $cache, static::ECM_INTERNAL_USER_META_KEYS ) ) ) );

		return $cache;
	}

	/**
	 * Custom fields defined inside one ECM config array (recursive so repeater
	 * and group sub-fields surface too).
	 *
	 * @return array<int,string>
	 */
	protected static function ecm_field_keys_from_config( array $config ): array {
		$keys = [];

		foreach ( (array) ( $config['custom_fields'] ?? [] ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$key = (string) ( $field['meta_key'] ?? $field['field_name'] ?? '' );
			if ( '' !== $key ) {
				$keys[] = $key;
			}

			$keys = array_merge( $keys, static::ecm_field_keys_from_config( $field ) );
		}

		return $keys;
	}

	/**
	 * Posts of one of ECM's internal config CPTs.
	 *
	 * @return array<int,\WP_Post|\stdClass>
	 */
	protected static function ecm_config_posts( string $internal_post_type ): array {
		$posts = get_posts(
			[
				'post_type'      => $internal_post_type,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		return is_array( $posts ) ? $posts : [];
	}

	/**
	 * ECM stores config arrays serialized into post_content (older installs may
	 * carry JSON) — normalize both into an array.
	 */
	protected static function ecm_decode_config( $post ): array {
		$config = static::ecm_maybe_unserialize( $post->post_content ?? '' );

		if ( is_string( $config ) ) {
			$decoded = json_decode( $config, true );
			$config  = is_array( $decoded ) ? $decoded : [];
		}

		return is_array( $config ) ? $config : [];
	}

	/**
	 * Whether did_action() is available (the test mocks don't provide it).
	 */
	protected static function ecm_did_action( string $hook ): bool {
		return function_exists( 'did_action' ) && (int) did_action( $hook ) > 0;
	}

	/**
	 * Standardized payload for a post/item, merged with any extras.
	 */
	protected static function ecm_item_payload( int $post_id, array $extra = [] ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return $extra;
		}

		$author = ! empty( $post->post_author ) ? get_userdata( (int) $post->post_author ) : null;

		$payload = [
			'item_id'     => (int) $post_id,
			'post_id'     => (int) $post_id,
			'title'       => (string) ( $post->post_title ?? '' ),
			'slug'        => (string) ( $post->post_name ?? '' ),
			'status'      => (string) ( $post->post_status ?? '' ),
			'post_type'   => (string) ( $post->post_type ?? '' ),
			'content'     => (string) ( $post->post_content ?? '' ),
			'excerpt'     => (string) ( $post->post_excerpt ?? '' ),
			'author_id'   => (int) ( $post->post_author ?? 0 ),
			'author_name' => $author ? (string) ( $author->display_name ?? '' ) : '',
			'permalink'   => (string) get_permalink( $post_id ),
			'created_at'  => (string) ( $post->post_date ?? '' ),
			'modified_at' => (string) ( $post->post_modified ?? '' ),
		];

		return array_merge( $payload, $extra );
	}

	/**
	 * Own maybe_unserialize: some environments (and Zaplane's test mocks) ship
	 * a pass-through maybe_unserialize, so deserialization can't be taken for
	 * granted. ECM writes list-shaped meta through maybe_serialize(), and the
	 * meta-diff triggers must be able to read those strings back.
	 */
	protected static function ecm_maybe_unserialize( $value ) {
		if ( is_string( $value ) && strlen( $value ) > 1 ) {
			$unserialized = @unserialize( $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

			if ( false !== $unserialized || 'b:0;' === $value ) {
				return $unserialized;
			}
		}

		return $value;
	}

	/**
	 * Snapshot the pre-update value of a meta key. `update_post_meta` fires
	 * before the row is written, so get_post_meta() still returns the old value
	 * there; `added_post_meta` means the meta did not exist before.
	 *
	 * @return array<int,mixed>
	 */
	protected static function ecm_old_meta_list( string $hook, int $object_id, string $meta_key ): array {
		if ( 'added_post_meta' === $hook ) {
			return [];
		}

		$old = get_post_meta( $object_id, $meta_key, true );
		$old = static::ecm_maybe_unserialize( $old );

		return is_array( $old ) ? $old : [];
	}
}
