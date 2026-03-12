<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Framework\Database\ORM\Collection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Integrations\Wordpress\QueryTrait;
use Zaplane\Framework\Models\User;
use Zaplane\Framework\Models\Post;

trait Helper {


	public static function get_user_payload( $user_ref, array $extra_data = [] ): ?array {
		$user = null;
		if ( is_numeric( $user_ref ) ) {
			$user = User::find( (int) $user_ref );
		} elseif ( is_object( $user_ref ) && $user_ref instanceof User ) {
			$user = $user_ref;
		} elseif ( is_object( $user_ref ) ) {
			$user = User::find( $user_ref->ID ?? 0 );
		} else {
			$user = User::byLogin( (string) $user_ref );
		}

		if ( ! $user ) {
			return null;
		}

		return array_merge( $user->toArray(), $extra_data );
	}

	public static function get_term_payload( $term_id, $taxonomy, $term_taxonomy_id = 0, array $extra_data = [], $term_object = null ) {
		if ( $term_object ) {
			$term_id = $term_object->term_id;
			$taxonomy = $term_object->taxonomy;
		}

		$include = [];
		if ( $term_id ) {
			$include[] = (int) $term_id;
		}

		$terms = self::query_terms([
			'where' => [
				'taxonomy' => $taxonomy,
				'include' => $include,
			],
			'limit' => 1,
		]);

		$term = [];
		if ( ! empty( $terms ) ) {
			$term = $terms[0];
		}

		if ( $extra_data ) {
			$term = array_merge( $term, $extra_data );
		}

		return $term;
	}

	public static function normalize_list( $value ): array {
		if ( is_string( $value ) ) {
			return array_map( 'trim', explode( ',', $value ) );
		}

		return (array) $value;
	}

	public static function normalize_caps( $value ): array {
		return array_fill_keys( self::normalize_list( $value ), true );
	}

	public static function normalize_taxonomy_args( $value ): array {
		return is_array( $value ) ? $value : [];
	}

	public static function set_role_display_name( string $role_key, string $display_name ): void {
		$roles = wp_roles();
		$roles->roles[ $role_key ]['name'] = $display_name;
		$roles->role_names[ $role_key ] = $display_name;
		update_option( $roles->role_key, $roles->roles, true );
	}

	public static function format_role_payload( string $role_key, $role ): ?array {
		if ( ! $role ) {
			return null;
		}

		return [
			'role' => $role_key,
			'display_name' => $role->name,
			'capabilities' => array_keys( $role->capabilities ),
		];
	}

	public static function resolve_role_key( $role_input, bool $require_existing = false ): string {
		$role = trim( (string) $role_input );
		if ( '' === $role ) {
			return '';
		}
		$roles = wp_roles();
		$sanitized_key = sanitize_key( $role );
		if ( isset( $roles->roles[ $sanitized_key ] ) ) {
			return $sanitized_key;
		}

		foreach ( $roles->role_names as $existing_key => $name ) {
			if ( strcasecmp( (string) $name, $role ) === 0 ) {
				return $existing_key;
			}
		}

		return $require_existing ? '' : $sanitized_key;
	}

	public static function copy_taxonomies( int $from_post, int $to_post ): void {
		$taxonomies = get_object_taxonomies( get_post_type( $from_post ) );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms(
				$from_post,
				$taxonomy,
				[ 'fields' => 'slug' ]
			);
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $to_post, $terms, $taxonomy );
			}
		}
	}

	public static function copy_meta( int $from_post, int $to_post ): void {
		$meta = get_post_meta( $from_post );

		foreach ( $meta as $key => $values ) {
			foreach ( $values as $value ) {
				add_post_meta(
					$to_post,
					$key,
					maybe_unserialize( $value )
				);
			}
		}
	}

	public static function get_posts( array $args = [] ): array {
		if ( ! empty( $args['post_id'] ) ) {
			$post = Post::find( (int) $args['post_id'] );
			return $post ? $post->toArray() : [];
		}

		$query = Post::query();

		if ( ! empty( $args['post_type'] ) ) {
			$query->where( 'post_type', $args['post_type'] );
		} else {
			$query->where( 'post_type', 'post' );
		}

		if ( ! empty( $args['post_status'] ) ) {
			$query->where( 'post_status', $args['post_status'] );
		}

		$posts = $query->get();

		return array_map( fn( $post) => $post->toArray(), $posts );
	}

	public static function format_post( $post ): array {
		if ( $post instanceof Post ) {
			return $post->toArray();
		}

		if ( $post instanceof \WP_Post ) {
			$model = Post::find( $post->ID );
			return $model ? $model->toArray() : [];
		}

		return [];
	}

	public static function get_post_types(): array {
		$types = get_post_types( [], 'objects' );
		$post_types = [];

		foreach ( $types as $name => $obj ) {
			$post_types[ $name ] = [
				'name'            => $obj->name,
				'label'           => $obj->label,
				'description'     => $obj->description,
				'hierarchical'    => $obj->hierarchical,
				'rest_base'       => $obj->rest_base,
				'show_in_rest'    => $obj->show_in_rest,
				'public'          => $obj->public,
				'capability_type' => $obj->capability_type,
				'capabilities'    => $obj->cap,
				'labels'          => (array) $obj->labels,
				'supports'        => $obj->supports ?? [],
				'menu_icon'       => $obj->menu_icon ?? '',
				'menu_position'   => $obj->menu_position ?? null,
			];
		}
		return $post_types;
	}

	public static function get_post_type_by_post_id( int $post_id ): array {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return [];
		}

		$obj = get_post_type_object( $post_type );
		if ( ! $obj ) {
			return [];
		}
		return [
			'post_id'         => $post_id,
			'post_type'       => $post_type,
			'label'           => $obj->label,
			'public'          => $obj->public,
			'show_in_rest'    => $obj->show_in_rest,
			'rest_base'       => $obj->rest_base,
			'capability_type' => $obj->capability_type,
			'hierarchical'    => $obj->hierarchical,
			'supports'        => $obj->supports ?? [],
			'description'     => $obj->description,
			'menu_icon'       => $obj->menu_icon ?? '',
			'menu_position'   => $obj->menu_position ?? null,
			'capabilities'    => $obj->cap ?? [],
			'labels'          => (array) $obj->labels,
		];
	}

	public static function register_post_type( array $config ): array {
		$slug               = $config['slug'] ?? '';
		if ( ! $slug ) {
			return [ 'error' => 'Post type slug is required' ];
		}

		$label              = $config['label'] ?? ucfirst( $slug );
		$hierarchical       = $config['hierarchical'] ?? false;
		$public             = $config['public'] ?? true;
		$show_in_rest       = $config['show_in_rest'] ?? true;
		$show_ui            = $config['show_ui'] ?? true;
		$show_in_menu       = $config['show_in_menu'] ?? true;
		$show_in_nav_menus  = $config['show_in_nav_menus'] ?? true;
		$show_in_admin_bar  = $config['show_in_admin_bar'] ?? true;
		$menu_icon          = $config['menu_icon'] ?? '';
		$menu_position      = $config['menu_position'] ?? null;
		$supports           = $config['supports'] ?? [ 'title', 'editor' ];
		$capability_type    = $config['capability_type'] ?? 'post';
		$description        = $config['description'] ?? '';
		$rewrite_slug       = $config['rewrite_slug'] ?? $slug;
		$args = [
			'label'             => $label,
			'public'            => $public,
			'hierarchical'      => $hierarchical,
			'show_ui'           => $show_ui,
			'show_in_menu'      => $show_in_menu,
			'show_in_nav_menus' => $show_in_nav_menus,
			'show_in_admin_bar' => $show_in_admin_bar,
			'menu_icon'         => $menu_icon,
			'menu_position'     => $menu_position,
			'supports'          => $supports,
			'capability_type'   => $capability_type,
			'description'       => $description,
			'show_in_rest'      => $show_in_rest,
			'rewrite'           => [ 'slug' => $rewrite_slug ],
		];
		$result = register_post_type( $slug, $args );
		if ( is_wp_error( $result ) ) {
			return [ 'error' => $result->get_error_message() ];
		}
		return [
			'success' => true,
			'post_type' => $slug,
			'args' => $args
		];
	}

	public static function upload_media_from_url( string $image_url, string $title = '', string $alt = '', string $caption = '', string $description = '' ) {

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, 0, $title, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		wp_update_post([
			'ID'           => $attachment_id,
			'post_title'   => $title,
			'post_excerpt' => $caption,
			'post_content' => $description,
		]);
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
		return [
			'attachment_id'    => $attachment_id,
			'image_url'        => wp_get_attachment_url( $attachment_id ),
			'image_title'      => $title,
			'alternative_text' => $alt,
			'caption'          => $caption,
			'description'      => $description,
		];
	}

	public static function get_media_posts( array $args = [] ): Collection {
		$query = Post::where( 'post_type', 'attachment' )
			->where( 'post_status', 'inherit' )
			->orderBy( 'post_date', 'desc' );

		if ( ! empty( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
			$query->limit( $args['posts_per_page'] );
		}

		return $query->get();
	}

	public static function format_media_items( array $media_posts ): array {
		return array_map(function ( $media ) {
			$mediaArray = $media instanceof Post ? $media->toArray() : (array) $media;
			$mediaId = $mediaArray['ID'] ?? 0;

			return array_merge($mediaArray, [
				'url'      => wp_get_attachment_url( $mediaId ),
				'alt_text' => get_post_meta( $mediaId, '_wp_attachment_image_alt', true ),
				'caption'  => wp_get_attachment_caption( $mediaId ),
			]);
		}, $media_posts);
	}
}
