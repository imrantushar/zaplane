<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait PostActionsTrait {

	protected static function action_create_post( array $config ): array {
		$id = wp_insert_post([
			'post_title'   => $config['post_title'] ?? '',
			'post_content' => $config['post_content'] ?? '',
			'post_status'  => $config['post_status'] ?? 'draft',
			'post_type'    => $config['post_type'] ?? 'post',
		]);

		if ( is_wp_error( $id ) ) {
			return static::error( $id->get_error_message() );
		}
		return static::success( [ 'post_id' => $id ] );
	}

	protected static function action_update_title( array $config ): array {
		$id = wp_update_post([
			'ID'         => $config['post_id'],
			'post_title' => $config['post_title'],
		]);

		if ( is_wp_error( $id ) ) {
			return static::error( $id->get_error_message() );
		}
		return static::success( [ 'post_id' => $id ] );
	}

	protected static function action_update_status( array $config ): array {
		$id = wp_update_post([
			'ID'          => $config['post_id'],
			'post_status' => $config['post_status'],
		]);

		if ( is_wp_error( $id ) ) {
			return static::error( $id->get_error_message() );
		}
		return static::success( [ 'post_id' => $id ] );
	}

	protected static function action_update_post( array $config ): array {
		$id = wp_update_post([
			'ID'           => $config['post_id'],
			'post_title'   => $config['post_title'],
			'post_type'    => $config['post_type'],
			'post_content' => $config['post_content'],
			'post_status'  => $config['post_status'],
		]);

		if ( is_wp_error( $id ) ) {
			return static::error( $id->get_error_message() );
		}
		return static::success( [ 'post_id' => $id ] );
	}

	protected static function action_duplicate_post( array $config ): array {
		$post_id     = $config['post_id'] ?? 0;
		$new_title   = $config['new_title'] ?? '';
		$status      = $config['status'] ?? 'draft';
		$post        = get_post( $post_id );
		if ( ! $post ) {
			return static::error( 'Post not found.' );
		}
		$final_title = '' !== $new_title ? $new_title : $post->post_title . ' (copy)';
		$new_post_id = wp_insert_post([
			'post_type'    => $post->post_type,
			'post_title'   => $final_title,
			'post_content' => $post->post_content,
			'post_status'  => $status,
			'post_author'  => $post->post_author,
		], true);

		if ( is_wp_error( $new_post_id ) ) {
			return static::error( $new_post_id->get_error_message() );
		}
		self::copy_taxonomies( $post_id, $new_post_id );
		self::copy_meta( $post_id, $new_post_id );
		return static::success([
			'original_id' => $post_id,
			'new_id' => $new_post_id,
			'new_title' => $final_title,
		]);
	}

	protected static function action_schedule_post( array $config ): array {
		$post_id       = $config['post_id'] ?? 0;
		$schedule_date = $config['schedule_date'] ?? '';
		$status        = $config['status'] ?? 'future';
		$post_data     = wp_update_post([
			'ID'            => $post_id,
			'post_status'   => $status,
			'post_date'     => $schedule_date,
			'post_date_gmt' => get_gmt_from_date( $schedule_date ),
		], true);

		if ( is_wp_error( $post_data ) ) {
			return static::error( $post_data->get_error_message() );
		}
		return static::success( [ 'post_id' => $post_id ] );
	}

	protected static function action_unschedule_post( array $config ): array {
		$post_id       = $config['post_id'] ?? 0;
		$post_data     = wp_update_post([
			'ID'            => $post_id,
			'post_status'   => 'draft',
			'post_date'     => current_time( 'mysql' ),
			'post_date_gmt' => current_time( 'mysql', 1 ),
		], true);

		if ( is_wp_error( $post_data ) ) {
			return static::error( $post_data->get_error_message() );
		}
		return static::success( [ 'post_id' => $post_id ] );
	}

	protected static function action_update_post_feature_image( array $config ): array {
		$post_id  = $config['post_id'] ?? 0;
		$image_id = $config['image_id'] ?? 0;
		if ( $post_id && $image_id ) {
			set_post_thumbnail( $post_id, $image_id );
		}
		return static::success( [ 'post_id' => $post_id ] );
	}

	protected static function action_change_post_author( array $config ): array {
		$post_id   = $config['post_id'] ?? 0;
		$author_id = $config['author_id'] ?? 0;
		$result    = wp_update_post([
			'ID'        => $post_id,
			'post_author' => $author_id
		], true);

		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}
		return static::success( [ 'post_id' => $post_id ] );
	}

	protected static function action_trash_post( array $config ): array {
		$result = wp_trash_post( $config['post_id'] ?? 0 );
		if ( ! $result ) {
			return static::error( 'Failed to trash post' );
		}
		return static::success( [ 'post_id' => $config['post_id'] ] );
	}

	protected static function action_restore_post( array $config ): array {
		$result = wp_untrash_post( $config['post_id'] ?? 0 );
		if ( ! $result ) {
			return static::error( 'Failed to restore post' );
		}
		return static::success( [ 'post_id' => $config['post_id'] ] );
	}

	protected static function action_untrash_post( array $config ): array {
		$result = wp_untrash_post( $config['post_id'] ?? 0 );
		if ( ! $result ) {
			return static::error( 'Failed to untrash post' );
		}
		return static::success( [ 'post_id' => $config['post_id'] ] );
	}

	protected static function action_delete_trash_post( array $config ): array {
		$result = wp_delete_post( $config['post_id'] ?? 0 );
		if ( ! $result ) {
			return static::error( 'Failed to delete trash post' );
		}
		return static::success( [ 'post_id' => $config['post_id'] ] );
	}

	protected static function action_delete_post( array $config ): array {
		$result = wp_delete_post( $config['post_id'] ?? 0 );
		if ( ! $result ) {
			return static::error( 'Failed to delete trash post' );
		}
		return static::success( [ 'post_id' => $config['post_id'] ] );
	}

	protected static function action_get_posts_all( array $config ): array {
		return static::success(
			self::get_posts()
		);
	}

	protected static function action_get_post_single( array $config ): array {
		return static::success(
			self::get_posts([
				'post_id' => $config['post_id'] ?? 0
			])
		);
	}

	protected static function action_get_posts_by_post_type( array $config ): array {
		return static::success(
			self::get_posts([
				'post_type' => $config['post_type'] ?? 'post'
			])
		);
	}

	protected static function action_get_posts_by_metadata( array $config ): array {
		return static::success(
			self::get_posts([
				'post_type' => $config['post_type'] ?? 'post',
				'meta_query' => [
					[
						'key'     => $config['meta_key'] ?? '',
						'value'   => $config['meta_value'] ?? '',
						'compare' => '=',
					],
				],
			])
		);
	}

	protected static function action_get_post_metadata_single( array $config ): array {
		$post_id    = $config['post_id'] ?? 0;
		$meta_key   = $config['meta_key'] ?? '';
		$meta_value = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );
		if ( ! $meta_value ) {
			return static::error( 'No meta value found for this key' );
		}
		return static::success([
			'post_id'    => $post_id,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		]);
	}

	protected static function action_get_post_permalink( array $config ): array {
		$post_id   = $config['post_id'] ?? 0;
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return static::error( 'No permalink found for this key' );
		}
		return static::success([
			'post_id'   => $post_id,
			'permalink' => $permalink,
		]);
	}

	protected static function action_get_post_content( array $config ): array {
		$post_id = $config['post_id'] ?? 0;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return static::error( 'No post found for this ID' );
		}
		return static::success([
			'post_id'      => $post_id,
			'post_content' => $post->post_content,
		]);
	}

	protected static function action_get_post_excerpt( array $config ): array {
		$post_id = $config['post_id'] ?? 0;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return static::error( 'No post found for this ID' );
		}
		return static::success([
			'post_id'      => $post_id,
			'post_excerpt' => $post->post_excerpt,
		]);
	}

	protected static function action_get_post_status( array $config ): array {
		$post_id = $config['post_id'] ?? 0;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return static::error( 'No post found for this ID' );
		}
		return static::success([
			'post_id'      => $post_id,
			'post_status' => $post->post_status,
		]);
	}
	protected static function action_get_post_type_all( array $config ): array {
		$post_types = self::get_post_types();
		return static::success( $post_types );
	}
	protected static function action_get_post_type_single( array $config ): array {
		$post_id = absint( $config['post_id'] ?? 0 );
		$post_type_info = self::get_post_type_by_post_id( $post_id );
		if ( empty( $post_type_info ) ) {
			return static::error( 'Post type not found for this post' );
		}
		return static::success( $post_type_info );
	}

	protected static function action_register_post_type( array $config ): array {
		$result = self::register_post_type( $config );
		if ( isset( $result['error'] ) ) {
			return static::error( $result['error'] );
		}
		return static::success([
			'post_type' => $result['post_type'],
			'args'      => $result['args'],
		]);
	}

	protected static function action_unregister_post_type( array $config ): array {
		$post_type = $config['post_type'] ?? '';
		$result    = unregister_post_type( $post_type );
		if ( ! $result ) {
			return static::error( "Failed to unregister post type : {$post_type}" );
		}
		return static::success([
			'post_type' => $post_type,
			'result'   => $result,
		]);
	}

	protected static function action_add_post_type_support( array $config ): array {
		$post_type = $config['post_type'] ?? '';
		$features  = $config['features'] ?? [];
		foreach ( $features as $feature ) {
			add_post_type_support( $post_type, $feature );
		}
		return static::success([
			'post_type' => $post_type,
			'added'     => $features,
		]);
	}

	protected static function action_set_featured_image( array $config ): array {
		$result = set_post_thumbnail( $config['post_id'] ?? 0, $config['attachment_id'] ?? 0 );
		if ( ! $result ) {
			return static::error( 'Failed to set featured image' );
		}
		return static::success([
			'post_id' => $config['post_id'],
			'attachment_id' => $config['attachment_id']
		]);
	}

	protected static function action_add_taxonomy_to_post( array $config ): array {
		$added = wp_set_object_terms(
			$config['post_id'] ?? 0,
			self::normalize_list( $config['terms'] ?? [] ),
			$config['taxonomy'] ?? '',
			$config['append'] ?? false
		);
		return static::success( [ 'added' => $added ] );
	}

	protected static function action_remove_taxonomy_from_post( array $config ): array {
		$removed = wp_remove_object_terms(
			$config['post_id'] ?? 0,
			self::normalize_list( $config['terms'] ?? [] ),
			$config['taxonomy'] ?? ''
		);
		return static::success( [ 'removed' => $removed ] );
	}

	protected static function action_bulk_assign_terms_to_posts( array $config ): array {
		$results = [];
		$post_ids = self::normalize_list( $config['post_ids'] ?? [] );
		$terms = self::normalize_list( $config['terms'] ?? [] );
		$taxonomy = $config['taxonomy'] ?? '';
		$append   = $config['append'] ?? false;

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );
		}

		return static::success( $results );
	}

	protected static function action_bulk_remove_terms_from_posts( array $config ): array {
		$results = [];
		$post_ids = self::normalize_list( $config['post_ids'] ?? [] );
		$terms = self::normalize_list( $config['terms'] ?? [] );
		$taxonomy = $config['taxonomy'] ?? '';

		foreach ( $post_ids as $post_id ) {
			$results[ $post_id ] = wp_remove_object_terms( $post_id, $terms, $taxonomy );
		}

		return static::success( $results );
	}

	protected static function action_add_category_to_post( array $config ): array {
		$added = wp_set_post_categories(
			$config['post_id'] ?? 0,
			self::normalize_list( $config['categories'] ?? [] ),
			$config['append'] ?? false
		);
		return static::success( [ 'added' => $added ] );
	}

	protected static function action_add_tags_to_post( array $config ): array {
		$added = wp_set_post_tags(
			$config['post_id'] ?? 0,
			self::normalize_list( $config['tags'] ?? [] ),
			$config['append'] ?? false
		);
		return static::success( [ 'added' => $added ] );
	}

	protected static function action_remove_tags_from_post( array $config ): array {
		$removed = wp_remove_object_terms(
			$config['post_id'] ?? 0,
			self::normalize_list( $config['tags'] ?? [] ),
			'post_tag'
		);
		return static::success( [ 'removed' => $removed ] );
	}
}
