<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Helper tool — inspect an image, or resize it into the uploads folder.
 *
 * Sources may be an attachment ID, a local path, or a remote URL (downloaded to
 * a temp file first). Resizing uses WordPress's own image editor, so it works
 * with whichever backend (GD/Imagick) the site has.
 */
class ImageHelper extends IntegrationBase {

	public static function get_slug(): string {
		return 'image_helper';
	}

	public static function get_name(): string {
		return 'Image Helper';
	}

	public static function get_category(): string {
		return 'tool';
	}

	public static function get_icon(): string {
		return 'image-helper.svg';
	}

	public static function get_actions(): array {
		return [
			'info'   => [ 'label' => 'Get Image Info' ],
			'resize' => [ 'label' => 'Resize Image' ],
		];
	}

	public static function get_action_config_schema( string $action ): array {
		$source = [ 'key' => 'source', 'label' => 'Image (URL, path, or attachment ID)', 'type' => 'expression', 'required' => true ];

		if ( 'resize' === $action ) {
			return [
				$source,
				[ 'key' => 'width', 'label' => 'Max width (px)', 'type' => 'number' ],
				[ 'key' => 'height', 'label' => 'Max height (px)', 'type' => 'number' ],
				[ 'key' => 'crop', 'label' => 'Crop to exact size', 'type' => 'checkbox', 'default' => false ],
			];
		}

		return [ $source ];
	}

	public static function execute_node( array $node, array $input ): array {
		$action = $node['data']['event'] ?? 'info';
		$config = $node['data']['config'] ?? [];
		$source = (string) ( $config['source'] ?? '' );

		[ $file, $is_temp ] = self::resolve_local_file( $source );

		if ( null === $file ) {
			return self::error( 'Could not read the image source.' );
		}

		try {
			$data = 'resize' === $action ? self::do_resize( $file, $config ) : self::do_info( $file );
		} finally {
			if ( $is_temp && file_exists( $file ) ) {
				wp_delete_file( $file );
			}
		}

		return [ 'port' => 'main', 'data' => $data ];
	}

	/**
	 * @return array<string,mixed>
	 */
	protected static function do_info( string $file ): array {
		$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- getimagesize warns on non-images; we handle false.
		if ( false === $size ) {
			return self::error( 'The source is not a readable image.' )['data'];
		}

		return [
			'success'  => true,
			'width'    => (int) $size[0],
			'height'   => (int) $size[1],
			'mime'     => $size['mime'] ?? '',
			'filesize' => file_exists( $file ) ? (int) filesize( $file ) : null,
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	protected static function do_resize( string $file, array $config ): array {
		$width  = (int) ( $config['width'] ?? 0 ) ?: null;
		$height = (int) ( $config['height'] ?? 0 ) ?: null;
		$crop   = ! empty( $config['crop'] );

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return self::error( $editor->get_error_message() )['data'];
		}

		$resized = $editor->resize( $width, $height, $crop );
		if ( is_wp_error( $resized ) ) {
			return self::error( $resized->get_error_message() )['data'];
		}

		$upload   = wp_upload_dir();
		$basename = 'zaplane-resized-' . wp_generate_password( 6, false ) . '-' . basename( $file );
		$target   = trailingslashit( $upload['path'] ) . $basename;

		$saved = $editor->save( $target );
		if ( is_wp_error( $saved ) ) {
			return self::error( $saved->get_error_message() )['data'];
		}

		$dimensions = $editor->get_size();

		return [
			'success' => true,
			'url'     => trailingslashit( $upload['url'] ) . $saved['file'],
			'path'    => $saved['path'],
			'width'   => (int) ( $dimensions['width'] ?? 0 ),
			'height'  => (int) ( $dimensions['height'] ?? 0 ),
		];
	}

	/**
	 * Resolve a source to a readable local file path.
	 *
	 * @return array{0:?string,1:bool} [ path|null, is_temp ]
	 */
	protected static function resolve_local_file( string $source ): array {
		$source = trim( $source );
		if ( '' === $source ) {
			return [ null, false ];
		}

		// Attachment ID.
		if ( ctype_digit( $source ) ) {
			$path = get_attached_file( (int) $source );
			return [ $path && file_exists( $path ) ? $path : null, false ];
		}

		// Remote URL — download to a temp file.
		if ( preg_match( '#^https?://#i', $source ) ) {
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$tmp = download_url( $source );
			if ( is_wp_error( $tmp ) ) {
				return [ null, false ];
			}
			return [ $tmp, true ];
		}

		// Local path.
		return [ file_exists( $source ) ? $source : null, false ];
	}

	/**
	 * @return array{port:string,data:array}
	 */
	protected static function error( string $message ): array {
		return [ 'port' => 'main', 'data' => [ 'success' => false, 'error' => $message ] ];
	}
}
