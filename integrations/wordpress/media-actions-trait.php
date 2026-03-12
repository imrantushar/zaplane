<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait MediaActionsTrait {

	protected static function action_generate_attachment_metadata( array $config ): array {
		$file = get_attached_file( $config['attachment_id'] ?? 0 );
		$metadata = wp_generate_attachment_metadata( $config['attachment_id'] ?? 0, $file );
		wp_update_attachment_metadata( $config['attachment_id'] ?? 0, $metadata );
		return static::success( [
			'attachment_id' => $config['attachment_id'],
			'metadata' => $metadata
		] );
	}

	protected static function action_add_media_image( array $config ): array {
		$result = self::upload_media_from_url(
			$config['image_url'] ?? '',
			$config['image_title'] ?? '',
			$config['alternative_text'] ?? '',
			$config['caption'] ?? '',
			$config['description'] ?? ''
		);

		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}
		return static::success( $result );
	}

	protected static function action_delete_media( array $config ): array {
		$attachment_id = $config['attachment_id'] ?? 0;
		$force_delete  = $config['force_delete'] ?? false;
		$deleted        = wp_delete_attachment( $attachment_id, $force_delete );
		if ( ! $deleted ) {
			return static::error( "Failed to delete attachment ID {$attachment_id}" );
		}
		return static::success([
			'attachment_id' => $attachment_id,
			'deleted'       => true,
			'force_delete'  => $force_delete,
		]);
	}

	protected static function action_rename_media( array $config ): array {
		$media_id  = $config['media_id'] ?? 0;
		$new_title = $config['new_title'] ?? '';
		$result    = wp_update_post([
			'ID'         => $media_id,
			'post_title' => $new_title,
		], true);
		if ( is_wp_error( $result ) || ! $result ) {
			return static::error( "Failed to rename attachment ID {$media_id}" );
		}
		return static::success([
			'attachment_id' => $media_id,
			'post_title'    => $new_title,
		]);
	}

	protected static function action_get_media_all( array $config ): array {
		$media_posts = self::get_media_posts();
		$media_items = self::format_media_items( $media_posts->toArray() );
		return static::success([
			'media_items' => $media_items,
		]);
	}

	protected static function action_get_media_by_title( array $config ): array {
		$title       = $config['title'] ?? '';
		$media_posts = self::get_media_posts( [ 's' => $title ] );
		$media_items = self::format_media_items( $media_posts->toArray() );
		return static::success([
			'media_items' => $media_items,
		]);
	}

	protected static function action_get_media_by_id( array $config ): array {
		$media_id   = $config['media_id'] ?? 0;
		$media      = get_post( $media_id );
		$media_item = self::format_media_items( [ $media ] )[0];
		return static::success([
			'media_item' => $media_item,
		]);
	}

	protected static function action_regenerate_image_sizes( array $config ): array {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$file = get_attached_file( $config['attachment_id'] ?? 0 );
		$metadata = wp_generate_attachment_metadata( $config['attachment_id'] ?? 0, $file );
		wp_update_attachment_metadata( $config['attachment_id'] ?? 0, $metadata );
		return static::success([
			'attachment_id' => $config['attachment_id'],
			'metadata' => $metadata,
			'success' => ! empty( $metadata )
		]);
	}
}
