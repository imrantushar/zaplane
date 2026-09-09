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
	 * One trigger or action found by app and event, whichever bucket holds it.
	 *
	 * A caller working from a node has an app and an event but not necessarily
	 * which kind it is, and the two namespaces do not overlap for a given app.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function find_capability( string $slug, string $event ): ?array {
		foreach ( [ 'trigger', 'action' ] as $type ) {
			$capability = self::describe_capability( $slug, $type, $event );
			if ( null !== $capability ) {
				return $capability;
			}
		}

		return null;
	}

	/**
	 * Resolve one config field's allowed values.
	 *
	 * A `select` whose options live on the site — a course, a product, a form —
	 * carries a `dynamic` descriptor naming the integration and lookup that can
	 * answer for it, and which keys of each row are the value and the label. This
	 * runs that lookup, which is the same registry the editor's own pickers use.
	 *
	 * `resolved` says whether the lookup actually ran: a lookup can fail because
	 * the companion plugin is inactive, and a caller must be able to tell that
	 * apart from "there is nothing to choose from".
	 *
	 * @param array<string,mixed> $config The node's config so far — some lookups
	 *                                    depend on an earlier choice.
	 * @return array{field:?array<string,mixed>,dynamic:bool,resolved:bool,options:array<int,array<string,mixed>>,error:?string}|null
	 *         Null when the field is not part of that capability.
	 */
	public static function field_options( string $slug, string $event, string $field_key, array $config = [] ): ?array {
		$capability = self::find_capability( $slug, $event );
		if ( null === $capability ) {
			return null;
		}

		$field = null;
		foreach ( (array) $capability['schema'] as $candidate ) {
			if ( is_array( $candidate ) && ( $candidate['key'] ?? '' ) === $field_key ) {
				$field = $candidate;
				break;
			}
		}

		if ( null === $field ) {
			return null;
		}

		$base = [
			'field'    => $field,
			'dynamic'  => false,
			'resolved' => true,
			'options'  => [],
			'error'    => null,
		];

		// A fixed list ships with the app and needs no lookup.
		if ( empty( $field['dynamic'] ) || ! is_array( $field['dynamic'] ) ) {
			$options = [];
			foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
				if ( is_array( $option ) && array_key_exists( 'value', $option ) ) {
					$options[] = [
						'value' => $option['value'],
						'label' => (string) ( $option['label'] ?? $option['value'] ),
					];
				} elseif ( is_scalar( $option ) ) {
					$options[] = [
						'value' => $option,
						'label' => (string) $option,
					];
				}
			}

			return array_merge( $base, [ 'options' => $options ] );
		}

		$dynamic = $field['dynamic'];
		// The lookup can belong to another integration — a user picker reused
		// across apps — so the field names its own source.
		$source  = (string) ( $dynamic['integration'] ?? $slug );
		$query   = (string) ( $dynamic['query'] ?? '' );

		$integration = IntegrationLoader::get( $source );
		if ( ! $integration || ! method_exists( $integration, 'get_dynamic_queries' ) ) {
			return array_merge( $base, [
				'dynamic'  => true,
				'resolved' => false,
				'error'    => sprintf( 'App "%s" provides no option lookups; is its plugin active?', $source ),
			] );
		}

		$queries = $integration::get_dynamic_queries();
		if ( ! isset( $queries[ $query ] ) || ! is_callable( $queries[ $query ] ) ) {
			return array_merge( $base, [
				'dynamic'  => true,
				'resolved' => false,
				'error'    => sprintf( 'Lookup "%s" is not registered by app "%s".', $query, $source ),
			] );
		}

		try {
			$raw = call_user_func( $queries[ $query ], $config );
		} catch ( \Throwable $e ) {
			return array_merge( $base, [
				'dynamic'  => true,
				'resolved' => false,
				'error'    => sprintf( 'Lookup "%s" failed: %s', $query, $e->getMessage() ),
			] );
		}

		// `select` names which keys carry the value and the label; integrations
		// differ (`name`/`label` in one, `value`/`label` in another).
		$select    = array_values( (array) ( $dynamic['select'] ?? [] ) );
		$value_key = (string) ( $select[0] ?? 'value' );
		$label_key = (string) ( $select[1] ?? 'label' );

		$options = [];
		foreach ( (array) $raw as $row ) {
			$row = (array) $row;
			if ( ! array_key_exists( $value_key, $row ) ) {
				continue;
			}
			$options[] = [
				'value' => $row[ $value_key ],
				'label' => (string) ( $row[ $label_key ] ?? $row[ $value_key ] ),
			];
		}

		return array_merge( $base, [
			'dynamic' => true,
			'options' => $options,
		] );
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
