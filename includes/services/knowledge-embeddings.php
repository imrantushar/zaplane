<?php

namespace Zaplane\Services;

use Zaplane\Framework\Classes\AiHttp;
use Zaplane\Framework\Classes\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embeddings layer for Business Knowledge — turns text into vectors so retrieval
 * can match by meaning ("can I get my money back?" → a "Refund policy" entry),
 * not just shared keywords.
 *
 * Opt-in and fail-safe: if no provider is configured (or a call fails) it returns
 * null and the caller falls back to keyword scoring, so knowledge always works.
 * The `zaplane_embeddings_vector` filter lets you plug a local/self-hosted model
 * (and lets tests inject deterministic vectors) without any API key.
 */
class KnowledgeEmbeddings {

	public const OPTION = 'zaplane_embeddings_config';

	private const OPENAI_URL = 'https://api.openai.com/v1/embeddings';
	private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:embedContent';

	/**
	 * Effective config, merged over defaults and filterable. The API key is
	 * decrypted from storage here so callers always see plaintext in `api_key`.
	 *
	 * @return array{enabled:bool,provider:string,api_key:string,model:string}
	 */
	public static function config(): array {
		$raw = self::raw_option();

		$api_key = '';
		if ( ! empty( $raw['api_key_enc'] ) && Encryption::is_available() ) {
			try {
				$api_key = (string) ( Encryption::decrypt( (string) $raw['api_key_enc'] )['k'] ?? '' );
			} catch ( \Throwable $e ) {
				$api_key = '';
			}
		} elseif ( ! empty( $raw['api_key'] ) ) {
			// Legacy plaintext (pre-encryption) — still honored; migrated to
			// encrypted form on the next save().
			$api_key = (string) $raw['api_key'];
		}

		$cfg = [
			'enabled'  => ! empty( $raw['enabled'] ),
			'provider' => (string) ( $raw['provider'] ?? 'openai' ),
			'api_key'  => $api_key,
			'model'    => (string) ( $raw['model'] ?? '' ),
		];

		/**
		 * Filter the embeddings config (e.g. to source the key from elsewhere).
		 *
		 * @param array $cfg
		 */
		return apply_filters( 'zaplane_embeddings_config', $cfg );
	}

	/**
	 * Persist config with the API key encrypted at rest. A blank api_key keeps
	 * (and opportunistically migrates) the stored key.
	 *
	 * @param array{enabled?:bool,provider?:string,model?:string,api_key?:string} $cfg
	 */
	public static function save( array $cfg ): void {
		$existing = self::raw_option();

		$store = [
			'enabled'  => ! empty( $cfg['enabled'] ),
			'provider' => sanitize_key( (string) ( $cfg['provider'] ?? 'openai' ) ),
			'model'    => sanitize_text_field( (string) ( $cfg['model'] ?? '' ) ),
		];

		$new_key = isset( $cfg['api_key'] ) ? (string) $cfg['api_key'] : '';

		if ( '' !== $new_key ) {
			self::stash_key( $new_key, $store );
		} elseif ( ! empty( $existing['api_key_enc'] ) ) {
			// Keep the already-encrypted key.
			$store['api_key_enc'] = (string) $existing['api_key_enc'];
		} elseif ( ! empty( $existing['api_key'] ) ) {
			// Migrate a legacy plaintext key to encrypted form.
			self::stash_key( (string) $existing['api_key'], $store );
		}

		// Changing provider/model invalidates every stored vector (different
		// dimensionality/space). Drop them so backfill re-embeds with the new
		// model instead of leaving stale vectors that retrieval would skip.
		$model_changed = ( (string) ( $existing['provider'] ?? '' ) !== $store['provider'] )
			|| ( (string) ( $existing['model'] ?? '' ) !== $store['model'] );

		update_option( self::OPTION, $store, false );

		if ( $model_changed ) {
			self::invalidate_all_vectors();
		}
	}

	/** Null out every stored embedding so they get re-embedded with the current model. */
	private static function invalidate_all_vectors(): void {
		global $wpdb;
		$table = \Zaplane\Models\Knowledge::getTable();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET embedding = NULL WHERE embedding IS NOT NULL', $table ) );
	}

	/** @return array<string,mixed> */
	private static function raw_option(): array {
		$o = get_option( self::OPTION, [] );
		return is_array( $o ) ? $o : [];
	}

	/**
	 * Store a key encrypted when possible, plaintext only as a last resort (no
	 * OpenSSL). Mutates $store in place.
	 *
	 * @param array<string,mixed> $store
	 */
	private static function stash_key( string $key, array &$store ): void {
		if ( '' === $key ) {
			return;
		}
		if ( Encryption::is_available() ) {
			$store['api_key_enc'] = Encryption::encrypt( [ 'k' => $key ] );
		} else {
			$store['api_key'] = $key;
		}
	}

	/** Whether semantic retrieval can run right now. */
	public static function enabled(): bool {
		$cfg = self::config();
		if ( empty( $cfg['enabled'] ) ) {
			return false;
		}
		// A key, or a custom vector provider wired via the filter.
		return '' !== (string) $cfg['api_key'] || has_filter( 'zaplane_embeddings_vector' );
	}

	/** Default model per provider when none is set. */
	public static function model( array $cfg ): string {
		if ( ! empty( $cfg['model'] ) ) {
			return (string) $cfg['model'];
		}
		return 'gemini' === strtolower( (string) $cfg['provider'] ) ? 'text-embedding-004' : 'text-embedding-3-small';
	}

	/**
	 * Embed a single string. Returns a float vector, or null when embeddings are
	 * unavailable/failed (caller then relies on keyword scoring).
	 *
	 * @return array<int,float>|null
	 */
	public static function embed( string $text ): ?array {
		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}

		$cfg = self::config();

		/**
		 * Short-circuit with a custom/local embedding. Return a numeric array to
		 * use it; return null to fall through to the configured provider.
		 *
		 * @param array<int,float>|null $vector
		 * @param string                $text
		 * @param array                 $cfg
		 */
		$injected = apply_filters( 'zaplane_embeddings_vector', null, $text, $cfg );
		if ( is_array( $injected ) && ! empty( $injected ) ) {
			return array_map( 'floatval', $injected );
		}

		if ( empty( $cfg['enabled'] ) || '' === (string) $cfg['api_key'] ) {
			return null;
		}

		return 'gemini' === strtolower( (string) $cfg['provider'] )
			? self::embed_gemini( $text, $cfg )
			: self::embed_openai( $text, $cfg );
	}

	/**
	 * Embed many strings in as few API calls as possible. Returns a vector (or
	 * null on failure) for each input, in the same order. Used by backfill/sync so
	 * N entries cost ~N/batch requests instead of N.
	 *
	 * @param array<int,string> $texts
	 * @return array<int,array<int,float>|null>
	 */
	public static function embed_batch( array $texts ): array {
		if ( empty( $texts ) ) {
			return [];
		}

		// A custom vector provider is per-item only — honor it one at a time.
		if ( has_filter( 'zaplane_embeddings_vector' ) ) {
			return array_map( static fn( $t ) => self::embed( (string) $t ), $texts );
		}

		$cfg = self::config();
		if ( empty( $cfg['enabled'] ) || '' === (string) $cfg['api_key'] ) {
			return array_fill( 0, count( $texts ), null );
		}

		// Gemini's single endpoint has no array input here — loop it (still one
		// place, still resilient via AiHttp).
		if ( 'gemini' === strtolower( (string) $cfg['provider'] ) ) {
			return array_map( static fn( $t ) => self::embed_gemini( trim( (string) $t ), $cfg ), $texts );
		}

		return self::embed_batch_openai( $texts, $cfg );
	}

	/**
	 * @param array<int,string>                                    $texts
	 * @param array{provider:string,api_key:string,model:string}   $cfg
	 * @return array<int,array<int,float>|null>
	 */
	private static function embed_batch_openai( array $texts, array $cfg ): array {
		$out = array_fill( 0, count( $texts ), null );

		// OpenAI accepts an array `input`; results come back with an `index`.
		$res = AiHttp::request(
			self::OPENAI_URL,
			[
				'Authorization' => 'Bearer ' . $cfg['api_key'],
				'Content-Type'  => 'application/json',
			],
			[
				'model' => self::model( $cfg ),
				'input' => array_map( static fn( $t ) => (string) $t, array_values( $texts ) ),
			]
		);

		if ( null !== $res['error'] ) {
			return $out; // all null → caller counts them as failed and retries later
		}

		foreach ( (array) ( $res['data']['data'] ?? [] ) as $item ) {
			$idx = (int) ( $item['index'] ?? -1 );
			$vec = $item['embedding'] ?? null;
			if ( $idx >= 0 && $idx < count( $out ) && is_array( $vec ) && ! empty( $vec ) ) {
				$out[ $idx ] = array_map( 'floatval', $vec );
			}
		}

		return $out;
	}

	/**
	 * @param array<int,float>|null $a
	 * @param array<int,float>|null $b
	 */
	public static function cosine( ?array $a, ?array $b ): float {
		if ( empty( $a ) || empty( $b ) ) {
			return 0.0;
		}
		// Different dimensionality means different embedding models/spaces —
		// comparing them yields a meaningless score, so refuse rather than
		// silently truncate to the shorter length.
		if ( count( $a ) !== count( $b ) ) {
			return 0.0;
		}
		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;
		$n   = count( $a );
		for ( $i = 0; $i < $n; $i++ ) {
			$x    = (float) $a[ $i ];
			$y    = (float) $b[ $i ];
			$dot += $x * $y;
			$na  += $x * $x;
			$nb  += $y * $y;
		}
		if ( $na <= 0.0 || $nb <= 0.0 ) {
			return 0.0;
		}
		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}

	/**
	 * Identity of the embedding model that produced a vector: "provider:model".
	 * Stored alongside each vector so retrieval never mixes incompatible spaces.
	 *
	 * @param array<string,mixed>|null $cfg
	 */
	public static function tag( ?array $cfg = null ): string {
		$cfg = $cfg ?? self::config();
		return strtolower( (string) $cfg['provider'] ) . ':' . self::model( $cfg );
	}

	/**
	 * Encode a vector for storage, tagged with the model that made it. Format:
	 * {"m":"openai:text-embedding-3-small","v":[...]}. When $tag is null the
	 * current model tag is used.
	 */
	public static function encode( ?array $vector, ?string $tag = null ): ?string {
		if ( empty( $vector ) ) {
			return null;
		}
		if ( null === $tag ) {
			$tag = self::tag();
		}
		return wp_json_encode(
			[
				'm' => $tag,
				'v' => array_map( 'floatval', $vector ),
			]
		);
	}

	/**
	 * Decode a stored embedding back to a vector. Handles both the tagged format
	 * and legacy plain-array embeddings.
	 *
	 * @return array<int,float>|null
	 */
	public static function decode( $stored ): ?array {
		$parsed = self::parse( $stored );
		return $parsed['vector'];
	}

	/**
	 * Decode a stored embedding for scoring, enforcing model/dimension
	 * compatibility: returns the vector only when it is safe to compare against a
	 * query embedded with $current_tag / $query_dims — otherwise null, so the row
	 * falls back to keyword-only scoring instead of contributing a garbage cosine.
	 *
	 * @return array<int,float>|null
	 */
	public static function usable_vector( $stored, string $current_tag, int $query_dims ): ?array {
		$parsed = self::parse( $stored );
		$vec    = $parsed['vector'];
		if ( null === $vec ) {
			return null;
		}
		// Tagged vectors must match the current model exactly.
		if ( '' !== $parsed['model'] && $parsed['model'] !== $current_tag ) {
			return null;
		}
		// Legacy/untagged (or belt-and-suspenders): dimensions must match.
		if ( $query_dims > 0 && count( $vec ) !== $query_dims ) {
			return null;
		}
		return $vec;
	}

	/**
	 * Parse a stored embedding once into its model tag and vector.
	 *
	 * @return array{model:string,vector:array<int,float>|null}
	 */
	private static function parse( $stored ): array {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return [
				'model'  => '',
				'vector' => null,
			];
		}
		$d = json_decode( $stored, true );
		if ( ! is_array( $d ) || empty( $d ) ) {
			return [
				'model'  => '',
				'vector' => null,
			];
		}
		// Tagged format {m,v}.
		if ( isset( $d['v'] ) && is_array( $d['v'] ) ) {
			return [
				'model'  => (string) ( $d['m'] ?? '' ),
				'vector' => array_map( 'floatval', $d['v'] ),
			];
		}
		// Legacy plain array.
		return [
			'model'  => '',
			'vector' => array_map( 'floatval', $d ),
		];
	}

	/**
	 * @param array{provider:string,api_key:string,model:string} $cfg
	 * @return array<int,float>|null
	 */
	private static function embed_openai( string $text, array $cfg ): ?array {
		$res = AiHttp::request(
			self::OPENAI_URL,
			[
				'Authorization' => 'Bearer ' . $cfg['api_key'],
				'Content-Type'  => 'application/json',
			],
			[
				'model' => self::model( $cfg ),
				'input' => $text,
			]
		);

		if ( null !== $res['error'] ) {
			return null;
		}
		$vec = $res['data']['data'][0]['embedding'] ?? null;
		return is_array( $vec ) && ! empty( $vec ) ? array_map( 'floatval', $vec ) : null;
	}

	/**
	 * @param array{provider:string,api_key:string,model:string} $cfg
	 * @return array<int,float>|null
	 */
	private static function embed_gemini( string $text, array $cfg ): ?array {
		$model = self::model( $cfg );
		$url   = sprintf( self::GEMINI_URL, rawurlencode( $model ) );

		$res = AiHttp::request(
			$url,
			[
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $cfg['api_key'],
			],
			[
				'model'   => 'models/' . $model,
				'content' => [ 'parts' => [ [ 'text' => $text ] ] ],
			]
		);

		if ( null !== $res['error'] ) {
			return null;
		}
		$vec = $res['data']['embedding']['values'] ?? null;
		return is_array( $vec ) && ! empty( $vec ) ? array_map( 'floatval', $vec ) : null;
	}
}
