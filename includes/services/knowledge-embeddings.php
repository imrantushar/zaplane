<?php

namespace Zaplane\Services;

use Zaplane\Framework\Classes\AiHttp;
use Zaplane\Models\Connection;

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
 *
 * Credentials are never stored here directly — a `connection_id` points at a
 * saved AI Connection (the same ones the AI / AI Agent nodes use), so a key
 * entered once is reusable everywhere and stays in one encrypted place.
 */
class KnowledgeEmbeddings {

	public const OPTION = 'zaplane_embeddings_config';

	/** Providers with a real embeddings endpoint. Anthropic and WordPress Core
	 * AI have no embeddings API as of this writing, so they're excluded even
	 * though they're valid chat providers on an AI Connection. */
	public const SUPPORTED_PROVIDERS = [ 'openai', 'gemini', 'openai_compatible' ];

	private const OPENAI_URL = 'https://api.openai.com/v1/embeddings';
	private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:embedContent';

	/**
	 * Effective config, resolved against the linked Connection so callers always
	 * see plaintext credentials in `api_key`/`base_url`.
	 *
	 * @return array{enabled:bool,connection_id:int,provider:string,api_key:string,base_url:string,model:string}
	 */
	public static function config(): array {
		$raw = self::raw_option();

		$connection_id = (int) ( $raw['connection_id'] ?? 0 );
		$provider      = '';
		$api_key       = '';
		$base_url      = '';

		if ( $connection_id > 0 ) {
			$connection = Connection::find( $connection_id );
			if ( $connection && 'ai' === $connection->app ) {
				$creds    = $connection->getCredentials();
				$provider = strtolower( (string) ( $creds['provider'] ?? '' ) );
				$api_key  = (string) ( $creds['api_key'] ?? '' );
				$base_url = (string) ( $creds['base_url'] ?? '' );
			}
		}

		$cfg = [
			'enabled'       => ! empty( $raw['enabled'] ),
			'connection_id' => $connection_id,
			'provider'      => $provider,
			'api_key'       => $api_key,
			'base_url'      => $base_url,
			'model'         => (string) ( $raw['model'] ?? '' ),
		];

		/**
		 * Filter the embeddings config (e.g. to source the key from elsewhere).
		 *
		 * @param array $cfg
		 */
		return apply_filters( 'zaplane_embeddings_config', $cfg );
	}

	/**
	 * Persist config. The connection itself carries the credentials — this only
	 * stores which connection and model to use.
	 *
	 * @param array{enabled?:bool,connection_id?:int,model?:string} $cfg
	 */
	public static function save( array $cfg ): void {
		$existing = self::raw_option();

		$store = [
			'enabled'       => ! empty( $cfg['enabled'] ),
			'connection_id' => (int) ( $cfg['connection_id'] ?? 0 ),
			'model'         => sanitize_text_field( (string) ( $cfg['model'] ?? '' ) ),
		];

		// Changing the connection or model can change the embedding space
		// (different provider/model = different dimensionality). Compare the
		// resolved tag before/after and drop stale vectors so backfill re-embeds
		// instead of retrieval silently skipping incompatible ones.
		$old_tag = self::tag( self::resolve( (int) ( $existing['connection_id'] ?? 0 ), (string) ( $existing['model'] ?? '' ) ) );

		update_option( self::OPTION, $store, false );

		$new_tag = self::tag( self::config() );

		if ( $old_tag !== $new_tag ) {
			self::invalidate_all_vectors();
		}
	}

	/**
	 * Resolve a connection_id/model pair into the same shape config() returns,
	 * without touching the stored option. Used by save() to compare the old tag.
	 *
	 * @return array{provider:string,model:string}
	 */
	private static function resolve( int $connection_id, string $model ): array {
		$provider = '';
		if ( $connection_id > 0 ) {
			$connection = Connection::find( $connection_id );
			if ( $connection && 'ai' === $connection->app ) {
				$creds    = $connection->getCredentials();
				$provider = strtolower( (string) ( $creds['provider'] ?? '' ) );
			}
		}
		return [
			'provider' => $provider,
			'model'    => $model,
		];
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

	/** Whether semantic retrieval can run right now. */
	public static function enabled(): bool {
		if ( has_filter( 'zaplane_embeddings_vector' ) ) {
			return true; // a custom/local vector provider works with no connection at all
		}
		$cfg = self::config();
		if ( empty( $cfg['enabled'] ) ) {
			return false;
		}
		if ( ! in_array( (string) $cfg['provider'], self::SUPPORTED_PROVIDERS, true ) ) {
			return false;
		}
		if ( 'openai_compatible' === $cfg['provider'] && '' === (string) $cfg['base_url'] ) {
			return false;
		}
		return '' !== (string) $cfg['api_key'];
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

		if ( ! self::enabled() ) {
			return null;
		}

		return self::dispatch_embed( $text, $cfg );
	}

	/** @param array{provider:string,api_key:string,base_url:string,model:string} $cfg */
	private static function dispatch_embed( string $text, array $cfg ): ?array {
		switch ( strtolower( (string) $cfg['provider'] ) ) {
			case 'gemini':
				return self::embed_gemini( $text, $cfg );
			case 'openai_compatible':
				return self::embed_openai_compatible( $text, $cfg );
			case 'openai':
				return self::embed_openai( $text, $cfg );
			default:
				return null;
		}
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

		if ( ! self::enabled() ) {
			return array_fill( 0, count( $texts ), null );
		}

		$cfg = self::config();

		// Gemini's single endpoint has no array input here — loop it (still one
		// place, still resilient via AiHttp).
		if ( 'gemini' === strtolower( (string) $cfg['provider'] ) ) {
			return array_map( static fn( $t ) => self::embed_gemini( trim( (string) $t ), $cfg ), $texts );
		}

		if ( 'openai_compatible' === strtolower( (string) $cfg['provider'] ) ) {
			return self::embed_batch_openai( $texts, $cfg, rtrim( (string) $cfg['base_url'], '/' ) . '/embeddings' );
		}

		return self::embed_batch_openai( $texts, $cfg, self::OPENAI_URL );
	}

	/**
	 * @param array<int,string>                                            $texts
	 * @param array{provider:string,api_key:string,base_url:string,model:string} $cfg
	 * @return array<int,array<int,float>|null>
	 */
	private static function embed_batch_openai( array $texts, array $cfg, string $url ): array {
		$out = array_fill( 0, count( $texts ), null );

		// OpenAI (and OpenAI-compatible) accept an array `input`; results come
		// back with an `index`.
		$res = AiHttp::request(
			$url,
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
	 * An OpenAI-compatible endpoint's /embeddings — same request/response shape
	 * as OpenAI itself, just against $cfg['base_url'] (Ollama, OpenRouter, Azure,
	 * LM Studio, or any self-hosted server implementing the same API).
	 *
	 * @param array{provider:string,api_key:string,base_url:string,model:string} $cfg
	 * @return array<int,float>|null
	 */
	private static function embed_openai_compatible( string $text, array $cfg ): ?array {
		$base_url = trim( (string) ( $cfg['base_url'] ?? '' ) );
		if ( '' === $base_url ) {
			return null;
		}

		$res = AiHttp::request(
			rtrim( $base_url, '/' ) . '/embeddings',
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
