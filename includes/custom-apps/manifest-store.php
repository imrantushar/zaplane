<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistence for user-defined "Custom App" manifests.
 *
 * Every manifest is a plain array describing one integration (identity, auth,
 * actions, triggers). They all live inside a single, NON-autoloaded WordPress
 * option as a versioned envelope:
 *
 *     { "version": 1, "apps": { "<slug>": { ...manifest... } } }
 *
 * Stored as a JSON string (not PHP-serialized) so the blob stays human
 * readable and export/import is a straight passthrough. The option is not
 * autoloaded because manifests can get large and are only needed when the
 * integration loader or the builder UI asks for them.
 */
class ManifestStore {

	public const OPTION       = 'zaplane_custom_apps';
	public const SCHEMA_VERSION = 1;

	/**
	 * In-request cache of the decoded envelope so repeated reads during a single
	 * request (loader + controllers) don't re-hit the option store.
	 *
	 * @var array<string,mixed>|null
	 */
	protected static ?array $cache = null;

	/**
	 * Read and decode the full envelope, normalising legacy/empty shapes.
	 *
	 * @return array{version:int,apps:array<string,array>}
	 */
	protected static function envelope(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$raw     = get_option( self::OPTION, '' );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;

		if ( ! is_array( $decoded ) || ! isset( $decoded['apps'] ) || ! is_array( $decoded['apps'] ) ) {
			$decoded = [
				'version' => self::SCHEMA_VERSION,
				'apps'    => [],
			];
		}

		self::$cache = $decoded;
		return self::$cache;
	}

	/**
	 * Persist the envelope back to the option (non-autoloaded) and refresh cache.
	 */
	protected static function persist( array $envelope ): bool {
		$envelope['version'] = self::SCHEMA_VERSION;
		self::$cache         = $envelope;

		$json = wp_json_encode( $envelope );
		if ( false === $json ) {
			return false;
		}

		// add_option lets us mark the option non-autoloaded on first write;
		// update_option preserves that flag on subsequent writes.
		if ( false === get_option( self::OPTION, false ) ) {
			return add_option( self::OPTION, $json, '', 'no' );
		}

		if ( update_option( self::OPTION, $json ) ) {
			return true;
		}

		// update_option() returns false when the stored value is unchanged, which
		// is not a failure — confirm the option actually holds what we wrote.
		return get_option( self::OPTION ) === $json;
	}

	/**
	 * All manifests keyed by slug.
	 *
	 * @return array<string,array>
	 */
	public static function all(): array {
		return self::envelope()['apps'];
	}

	/**
	 * A single manifest by slug, or null when absent.
	 */
	public static function get( string $slug ): ?array {
		$apps = self::all();
		return $apps[ $slug ] ?? null;
	}

	public static function exists( string $slug ): bool {
		return isset( self::all()[ $slug ] );
	}

	/**
	 * Insert or replace a manifest. The manifest must already be validated and
	 * carry a non-empty `slug`.
	 */
	public static function save( array $manifest ): bool {
		$slug = isset( $manifest['slug'] ) ? (string) $manifest['slug'] : '';
		if ( '' === $slug ) {
			return false;
		}

		$envelope                   = self::envelope();
		$envelope['apps'][ $slug ] = $manifest;

		return self::persist( $envelope );
	}

	public static function delete( string $slug ): bool {
		$envelope = self::envelope();
		if ( ! isset( $envelope['apps'][ $slug ] ) ) {
			return false;
		}

		unset( $envelope['apps'][ $slug ] );
		return self::persist( $envelope );
	}

	/**
	 * Drop the in-request cache. Mainly for tests and long-running CLI processes.
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
