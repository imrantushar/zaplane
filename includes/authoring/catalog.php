<?php

namespace Zaplane\Authoring;

use Zaplane\CustomApps\ManifestStore;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Core\IntegrationManifest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only view over the integration manifest, shaped for callers that have to
 * *choose* a capability rather than render one.
 *
 * The dashboard ships the whole manifest to the browser and lets a human scroll
 * it. That doesn't work for a caller working from a sentence: there are ~950
 * triggers and actions across 90+ apps, and the useful operation is "find the
 * handful that could plausibly do this", then "show me exactly that one's
 * fields". Those are search() and describe_capability().
 *
 * Backed by the pre-built assets/json/integrations.json plus any Custom Apps
 * merged in live, since those register at runtime and are never written to the
 * static file.
 */
class Catalog {

	/** @var array<string,mixed>|null Per-request memo — the file is ~1.2MB. */
	private static ?array $manifest = null;

	/**
	 * The full manifest: built-in catalogue + live Custom Apps.
	 *
	 * @return array{apps:array<string,mixed>,tools:array<string,mixed>}
	 */
	public static function manifest(): array {
		if ( null !== self::$manifest ) {
			return self::$manifest;
		}

		$manifest = [
			'apps'  => [],
			'tools' => [],
		];

		$file = ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json';
		if ( is_readable( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$decoded = json_decode(
				str_replace(
					IntegrationManifest::REST_URL_TOKEN,
					rest_url(),
					(string) file_get_contents( $file )
				),
				true
			);
			if ( is_array( $decoded ) ) {
				$manifest = $decoded;
			}
		}

		$manifest['apps']  = $manifest['apps'] ?? [];
		$manifest['tools'] = $manifest['tools'] ?? [];

		foreach ( ManifestStore::all() as $slug => $unused ) {
			$slug        = (string) $slug;
			$integration = IntegrationLoader::get( $slug );
			if ( ! $integration ) {
				continue;
			}

			$entry = IntegrationManifest::build_entry( get_class( $integration ), $slug );
			if ( null === $entry ) {
				continue;
			}

			$bucket = 'tool' === $entry['category'] ? 'tools' : 'apps';
			$manifest[ $bucket ][ $slug ] = $entry;
		}

		self::$manifest = $manifest;

		return self::$manifest;
	}

	/** Drop the memo. Call after registering an integration mid-request (tests). */
	public static function flush(): void {
		self::$manifest = null;
	}

	/**
	 * Every entry, apps and tools together, keyed by slug.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$m = self::manifest();
		return $m['apps'] + $m['tools'];
	}

	/** One raw manifest entry, or null when the slug is unknown. */
	public static function entry( string $slug ): ?array {
		$all = self::all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Compact index of every app and tool — identity and capability counts only,
	 * no field schemas. Small enough to hand a caller whole (~90 rows).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_apps( string $category = '' ): array {
		$out = [];

		foreach ( self::all() as $slug => $entry ) {
			if ( '' !== $category && ( $entry['category'] ?? 'app' ) !== $category ) {
				continue;
			}

			$out[] = [
				'slug'                => (string) $slug,
				'name'                => (string) ( $entry['name'] ?? $slug ),
				'category'            => (string) ( $entry['category'] ?? 'app' ),
				'requires_connection' => (bool) ( $entry['requires_connection'] ?? false ),
				'trigger_count'       => count( (array) ( $entry['triggers'] ?? [] ) ),
				'action_count'        => count( (array) ( $entry['actions'] ?? [] ) ),
			];
		}

		usort( $out, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

		return $out;
	}

	/**
	 * One app with every trigger and action it exposes, each carrying its full
	 * config schema. This is the payload a caller needs to write a valid node.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function describe_app( string $slug ): ?array {
		$entry = self::entry( $slug );
		if ( null === $entry ) {
			return null;
		}

		return [
			'slug'                => (string) ( $entry['slug'] ?? $slug ),
			'name'                => (string) ( $entry['name'] ?? $slug ),
			'icon'                => (string) ( $entry['icon'] ?? '' ),
			'category'            => (string) ( $entry['category'] ?? 'app' ),
			'requires_connection' => (bool) ( $entry['requires_connection'] ?? false ),
			'auth_type'           => $entry['auth_type'] ?? 'none',
			'supports_webhook'    => (bool) ( $entry['supports_webhook'] ?? false ),
			'triggers'            => self::shape_capabilities( $slug, 'trigger', (array) ( $entry['triggers'] ?? [] ) ),
			'actions'             => self::shape_capabilities( $slug, 'action', (array) ( $entry['actions'] ?? [] ) ),
		];
	}

	/**
	 * A single trigger or action with its schema.
	 *
	 * @param string $type 'trigger' | 'action'.
	 * @return array<string,mixed>|null
	 */
	public static function describe_capability( string $slug, string $type, string $key ): ?array {
		$entry = self::entry( $slug );
		if ( null === $entry ) {
			return null;
		}

		$bucket = 'trigger' === $type ? 'triggers' : 'actions';
		$raw    = ( $entry[ $bucket ] ?? [] )[ $key ] ?? null;

		if ( ! is_array( $raw ) ) {
			return null;
		}

		return self::shape_capability( $slug, $type, $key, $raw );
	}

	/**
	 * Rank capabilities against a free-text query.
	 *
	 * Deliberately dumb — token overlap against slug/name/key/label, weighted so an
	 * app-name hit doesn't drown out the capability that actually matches. Good
	 * enough to cut ~950 candidates down to a shortlist the caller can then read
	 * schemas for; no index or embedding to keep in sync.
	 *
	 * @param string $type 'trigger' | 'action' | '' for both.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search( string $query, string $type = '', int $limit = 20 ): array {
		$tokens = self::tokenize( $query );
		if ( empty( $tokens ) ) {
			return [];
		}

		$scored = [];

		foreach ( self::all() as $slug => $entry ) {
			$app_haystack = self::tokenize( $slug . ' ' . ( $entry['name'] ?? '' ) );

			foreach ( [ 'trigger' => 'triggers', 'action' => 'actions' ] as $kind => $bucket ) {
				if ( '' !== $type && $type !== $kind ) {
					continue;
				}

				foreach ( (array) ( $entry[ $bucket ] ?? [] ) as $key => $cap ) {
					$cap_haystack = self::tokenize( $key . ' ' . ( $cap['label'] ?? '' ) );

					$score = ( 3 * self::overlap( $tokens, $cap_haystack ) )
						+ self::overlap( $tokens, $app_haystack );

					if ( $score <= 0 ) {
						continue;
					}

					$scored[] = [
						'score'    => $score,
						'app'      => (string) $slug,
						'app_name' => (string) ( $entry['name'] ?? $slug ),
						'type'     => $kind,
						'key'      => (string) $key,
						'label'    => (string) ( $cap['label'] ?? $key ),
						'requires_connection' => (bool) ( $entry['requires_connection'] ?? false ),
					];
				}
			}
		}

		usort(
			$scored,
			fn( $a, $b ) => $b['score'] <=> $a['score'] ?: strcasecmp( $a['label'], $b['label'] )
		);

		return array_slice( $scored, 0, max( 1, $limit ) );
	}

	/* --------------------------------------------------------------------- */

	/**
	 * @param array<string,mixed> $caps
	 * @return array<int,array<string,mixed>>
	 */
	private static function shape_capabilities( string $slug, string $type, array $caps ): array {
		$out = [];
		foreach ( $caps as $key => $cap ) {
			if ( ! is_array( $cap ) ) {
				continue;
			}
			$out[] = self::shape_capability( $slug, $type, (string) $key, $cap );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $cap
	 * @return array<string,mixed>
	 */
	private static function shape_capability( string $slug, string $type, string $key, array $cap ): array {
		$shaped = [
			'app'     => $slug,
			'type'    => $type,
			'key'     => $key,
			'label'   => (string) ( $cap['label'] ?? $key ),
			'schema'  => array_values( (array) ( $cap['schema'] ?? [] ) ),
			'outputs' => array_values( (array) ( $cap['outputs'] ?? [ 'main' ] ) ),
		];

		if ( 'trigger' === $type ) {
			$shaped['hook'] = (string) ( $cap['hook'] ?? '' );
		}

		// Optional flags an integration may set (disabled, requires_addon, …) are
		// meaningful to a caller deciding whether a capability is usable here.
		foreach ( [ 'disabled', 'requires_addon', 'description' ] as $flag ) {
			if ( isset( $cap[ $flag ] ) ) {
				$shaped[ $flag ] = $cap[ $flag ];
			}
		}

		return $shaped;
	}

	/** @return array<int,string> */
	private static function tokenize( string $text ): array {
		$text  = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1 $2', $text ) );
		$parts = preg_split( '/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $parts ) ) {
			return [];
		}

		// Stopwords that would otherwise match nearly every label.
		$stop = [ 'a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'and', 'or', 'is', 'when', 'new' ];

		return array_values( array_unique( array_diff( $parts, $stop ) ) );
	}

	/**
	 * @param array<int,string> $needles
	 * @param array<int,string> $haystack
	 */
	private static function overlap( array $needles, array $haystack ): int {
		$score = 0;
		foreach ( $needles as $needle ) {
			foreach ( $haystack as $hay ) {
				if ( $needle === $hay ) {
					$score += 2;
					continue 2;
				}
				if ( strlen( $needle ) > 3 && false !== strpos( $hay, $needle ) ) {
					$score += 1;
					continue 2;
				}
			}
		}
		return $score;
	}
}
