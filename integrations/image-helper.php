<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		$source = [
			'key' => 'source',
			'label' => 'Image (URL, attachment ID, or path in uploads)',
			'type' => 'expression',
			'required' => true
		];

		if ( 'resize' === $action ) {
			return [
				$source,
				[
					'key' => 'width',
					'label' => 'Max width (px)',
					'type' => 'number'
				],
				[
					'key' => 'height',
					'label' => 'Max height (px)',
					'type' => 'number'
				],
				[
					'key' => 'crop',
					'label' => 'Crop to exact size',
					'type' => 'checkbox',
					'default' => false
				],
			];
		}//end if

		return [ $source ];
	}

	public static function get_action_sample_output( string $action ): array {
		if ( 'resize' === $action ) {
			return [
				'success' => true,
				'url'     => 'https://example.com/wp-content/uploads/resized.png',
				'path'    => '',
				'width'   => 800,
				'height'  => 600,
			];
		}
		return [
			'success'  => true,
			'width'    => 1200,
			'height'   => 800,
			'mime'     => 'image/png',
			'filesize' => 20480,
		];
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

		return [
			'port' => 'main',
			'data' => $data
		];
	}

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

	protected static function do_resize( string $file, array $config ): array {
		$width  = (int) ( $config['width'] ?? 0 );
		$width  = $width > 0 ? $width : null;

		$height = (int) ( $config['height'] ?? 0 );
		$height = $height > 0 ? $height : null;

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

		// Local path: only files inside the uploads folder, so a step can't
		// read (or copy a resized version of) anything else on the server.
		return [ self::inside_uploads( $source ), false ];
	}

	/**
	 * The real path of $source when it is a file under the uploads folder;
	 * null for anything else, including stream wrappers (phar://, php://…).
	 */
	protected static function inside_uploads( string $source ): ?string {
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $source ) ) {
			return null;
		}
		$real = realpath( $source );
		$base = realpath( (string) ( wp_upload_dir()['basedir'] ?? '' ) );
		if ( ! $real || ! $base || ! is_file( $real ) ) {
			return null;
		}
		return 0 === strpos( $real, rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ) ? $real : null;
	}

	protected static function error( string $message ): array {
		return [
			'port' => 'main',
			'data' => [
				'success' => false,
				'error' => $message
			]
		];
	}
}
