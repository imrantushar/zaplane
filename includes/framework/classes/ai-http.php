<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resilient JSON-over-HTTP client shared by the AI integrations.
 *
 * Adds what a bare wp_remote_post lacks for provider APIs: automatic retry with
 * exponential backoff on rate limits (429) and transient server errors (5xx),
 * Retry-After awareness, normalized error messages, and provider-agnostic token
 * usage extraction. All AI calls (Chat Model, AI Agent, embeddings, transcription,
 * image) go through here so reliability is uniform.
 */
class AiHttp {

	/** Default per-request timeout (seconds). */
	private const DEFAULT_TIMEOUT = 120;

	/** Default number of RETRIES (total attempts = retries + 1). */
	private const DEFAULT_RETRIES = 2;

	/** Base backoff (seconds) for the first retry; doubles each attempt. */
	private const BASE_BACKOFF = 0.5;

	/** Never wait longer than this between attempts (seconds). */
	private const MAX_BACKOFF = 15.0;

	/**
	 * POST a JSON body and return a normalized result.
	 *
	 * @param string              $url     Endpoint.
	 * @param array<string,string> $headers Request headers.
	 * @param array<string,mixed>  $body    JSON-encodable body.
	 * @param array<string,mixed>  $opts    timeout, retries, method.
	 *
	 * @return array{status:int,data:array,error:?string,attempts:int}
	 */
	public static function request( string $url, array $headers, array $body, array $opts = [] ): array {
		$method = strtoupper( (string) ( $opts['method'] ?? 'POST' ) );
		return self::send(
			$url,
			[
				'method'  => $method,
				'headers' => $headers,
				'body'    => 'GET' === $method ? null : wp_json_encode( $body ),
				'timeout' => (int) ( $opts['timeout'] ?? self::DEFAULT_TIMEOUT ),
			],
			max( 0, (int) ( $opts['retries'] ?? self::DEFAULT_RETRIES ) )
		);
	}

	/**
	 * POST a multipart/form-data body (for file uploads like audio transcription).
	 *
	 * @param array<string,string>                                    $headers
	 * @param array<string,string>                                    $fields Simple text fields.
	 * @param array<string,array{filename:string,content:string,type?:string}> $files
	 *
	 * @return array{status:int,data:array,error:?string,attempts:int}
	 */
	public static function multipart( string $url, array $headers, array $fields, array $files, array $opts = [] ): array {
		$boundary          = '----zaplane' . md5( uniqid( 'zap', true ) );
		$headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;

		return self::send(
			$url,
			[
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => self::build_multipart( $boundary, $fields, $files ),
				'timeout' => (int) ( $opts['timeout'] ?? self::DEFAULT_TIMEOUT ),
			],
			max( 0, (int) ( $opts['retries'] ?? 1 ) )
		);
	}

	/**
	 * Encode fields + files as a multipart/form-data body.
	 *
	 * @param array<string,string>                                    $fields
	 * @param array<string,array{filename:string,content:string,type?:string}> $files
	 */
	public static function build_multipart( string $boundary, array $fields, array $files ): string {
		$eol  = "\r\n";
		$body = '';

		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
			$body .= (string) $value . $eol;
		}

		foreach ( $files as $name => $file ) {
			$filename = (string) ( $file['filename'] ?? 'file' );
			$type     = (string) ( $file['type'] ?? 'application/octet-stream' );
			$body    .= '--' . $boundary . $eol;
			$body    .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $filename . '"' . $eol;
			$body    .= 'Content-Type: ' . $type . $eol . $eol;
			$body    .= (string) ( $file['content'] ?? '' ) . $eol;
		}

		$body .= '--' . $boundary . '--' . $eol;
		return $body;
	}

	/**
	 * Shared send loop with retry/backoff, used by both request() and multipart().
	 *
	 * @param array<string,mixed> $args wp_remote_request args (method/headers/body/timeout).
	 *
	 * @return array{status:int,data:array,error:?string,attempts:int}
	 */
	private static function send( string $url, array $args, int $retries ): array {
		$attempt    = 0;
		$last_error = 'Request failed.';

		while ( true ) {
			++$attempt;

			$response = wp_remote_request( $url, $args );

			// Transport-level failure (DNS, connection, timeout): retryable.
			if ( is_wp_error( $response ) ) {
				$last_error = $response->get_error_message();
				if ( $attempt <= $retries ) {
					self::backoff( $attempt, null );
					continue;
				}
				return [
					'status'   => 0,
					'data'     => [],
					'error'    => $last_error,
					'attempts' => $attempt,
				];
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) ) {
				$data = [];
			}

			// Rate limited or transient server error: back off and retry.
			if ( ( 429 === $status || $status >= 500 ) && $attempt <= $retries ) {
				$last_error = self::error_text( $data, $status );
				self::backoff( $attempt, wp_remote_retrieve_header( $response, 'retry-after' ) );
				continue;
			}

			return [
				'status'   => $status,
				'data'     => $data,
				'error'    => $status >= 400 ? self::error_text( $data, $status ) : null,
				'attempts' => $attempt,
			];
		}//end while
	}

	/**
	 * Pull a human-readable error message out of a provider error body, falling
	 * back to the HTTP status. Handles Anthropic/OpenAI ({error:{message}}),
	 * Gemini ({error:{message}}), and plain-string error shapes.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function error_text( array $data, int $status ): string {
		$err = $data['error'] ?? null;
		if ( is_array( $err ) ) {
			$msg = $err['message'] ?? ( $err['type'] ?? '' );
			if ( '' !== (string) $msg ) {
				return (string) $msg;
			}
		} elseif ( is_string( $err ) && '' !== $err ) {
			return $err;
		}

		if ( isset( $data['message'] ) && is_string( $data['message'] ) && '' !== $data['message'] ) {
			return $data['message'];
		}

		return $status > 0 ? ( 'HTTP ' . $status ) : 'Request failed.';
	}

	/**
	 * Normalize token usage across providers.
	 *
	 * @param array<string,mixed> $data Raw provider response.
	 *
	 * @return array{input_tokens:int,output_tokens:int,total_tokens:int}
	 */
	public static function usage( string $provider, array $data ): array {
		$in  = 0;
		$out = 0;

		switch ( strtolower( $provider ) ) {
			case 'openai':
				$u   = $data['usage'] ?? [];
				$in  = (int) ( $u['prompt_tokens'] ?? 0 );
				$out = (int) ( $u['completion_tokens'] ?? 0 );
				break;

			case 'gemini':
			case 'google':
				$u   = $data['usageMetadata'] ?? [];
				$in  = (int) ( $u['promptTokenCount'] ?? 0 );
				$out = (int) ( $u['candidatesTokenCount'] ?? 0 );
				break;

			case 'anthropic':
			default:
				$u   = $data['usage'] ?? [];
				$in  = (int) ( $u['input_tokens'] ?? 0 );
				$out = (int) ( $u['output_tokens'] ?? 0 );
				// Anthropic reports cache tokens separately; count them as input.
				$in += (int) ( $u['cache_read_input_tokens'] ?? 0 );
				$in += (int) ( $u['cache_creation_input_tokens'] ?? 0 );
				break;
		}//end switch

		$total = (int) ( $data['usage']['total_tokens'] ?? ( $in + $out ) );

		return [
			'input_tokens'  => $in,
			'output_tokens' => $out,
			'total_tokens'  => $total > 0 ? $total : ( $in + $out ),
		];
	}

	/**
	 * Estimate USD cost for a call from its token usage. Rates are per 1M tokens
	 * and filterable (`zaplane_ai_pricing`) so they can be kept current or
	 * extended without a code change. Unknown models cost 0 (never guesses high).
	 *
	 * @param array{input_tokens:int,output_tokens:int,total_tokens:int} $usage
	 */
	public static function estimate_cost( string $model, array $usage ): float {
		$model = strtolower( trim( $model ) );

		// [ input_per_1m, output_per_1m ] — approximate list prices.
		$pricing = apply_filters(
			'zaplane_ai_pricing',
			[
				'claude-opus-4-8'         => [ 15.0, 75.0 ],
				'claude-sonnet-4-6'       => [ 3.0, 15.0 ],
				'claude-haiku-4-5'        => [ 1.0, 5.0 ],
				'gpt-4o'                  => [ 2.5, 10.0 ],
				'gpt-4o-mini'             => [ 0.15, 0.6 ],
				'text-embedding-3-small'  => [ 0.02, 0.0 ],
				'text-embedding-3-large'  => [ 0.13, 0.0 ],
			]
		);

		// Prefix match so dated variants (e.g. gpt-4o-2024-…) still resolve.
		$rate = null;
		if ( isset( $pricing[ $model ] ) ) {
			$rate = $pricing[ $model ];
		} else {
			foreach ( $pricing as $prefix => $r ) {
				if ( 0 === strpos( $model, (string) $prefix ) ) {
					$rate = $r;
					break;
				}
			}
		}

		if ( ! is_array( $rate ) ) {
			return 0.0;
		}

		$in  = ( (int) ( $usage['input_tokens'] ?? 0 ) / 1_000_000 ) * (float) $rate[0];
		$out = ( (int) ( $usage['output_tokens'] ?? 0 ) / 1_000_000 ) * (float) ( $rate[1] ?? 0.0 );

		return round( $in + $out, 6 );
	}

	/**
	 * Wrap text as an Anthropic system/content block with an ephemeral cache
	 * breakpoint. Anthropic caches the marked prefix (system prompt, injected
	 * knowledge, tool definitions) so repeat calls with the same prefix are
	 * billed at the cache rate. Short prefixes below the model minimum are simply
	 * not cached — no error — so this is always safe to apply.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function anthropic_cached_text( string $text ): array {
		return [
			[
				'type'          => 'text',
				'text'          => $text,
				'cache_control' => [ 'type' => 'ephemeral' ],
			],
		];
	}

	/**
	 * Sleep before the next attempt: honor a Retry-After header when present,
	 * otherwise exponential backoff. Capped so a run never stalls for long.
	 *
	 * @param mixed $retry_after Retry-After header value (seconds), if any.
	 */
	private static function backoff( int $attempt, $retry_after ): void {
		$seconds = self::BASE_BACKOFF * ( 2 ** ( $attempt - 1 ) );

		if ( null !== $retry_after && '' !== $retry_after && is_numeric( $retry_after ) ) {
			$seconds = max( $seconds, (float) $retry_after );
		}

		$seconds = min( $seconds, self::MAX_BACKOFF );
		if ( $seconds > 0 ) {
			usleep( (int) round( $seconds * 1_000_000 ) );
		}
	}
}
