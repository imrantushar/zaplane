<?php

namespace Zaplane\Framework\Cloud;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HMAC-signed HTTP client for plugin → cloud calls.
 *
 * Signature scheme (mirrors `App\Http\Middleware\VerifySiteHmac` on the
 * cloud side):
 *   header X-Zaplane-Site:      site_id
 *   header X-Zaplane-Timestamp: unix seconds
 *   header X-Zaplane-Signature: hex(hmac_sha256(timestamp + "\n" + body, secret))
 */
class HttpClient {

	public static function post_signed( string $url, array $body, int $site_id, string $secret, int $timeout = 15 ): array {
		$json      = wp_json_encode( $body );
		$timestamp = (string) time();
		$signature = hash_hmac( 'sha256', $timestamp . "\n" . $json, $secret );

		$response = wp_remote_post( $url, [
			'timeout'     => $timeout,
			'redirection' => 0,
			'blocking'    => true,
			'headers'     => [
				'Content-Type'        => 'application/json',
				'Accept'              => 'application/json',
				'X-Zaplane-Site'      => (string) $site_id,
				'X-Zaplane-Timestamp' => $timestamp,
				'X-Zaplane-Signature' => $signature,
			],
			'body' => $json,
		] );

		return self::normalize_response( $response );
	}

	public static function post_unsigned( string $url, array $body, int $timeout = 15 ): array {
		$response = wp_remote_post( $url, [
			'timeout'     => $timeout,
			'redirection' => 0,
			'blocking'    => true,
			'headers'     => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body' => wp_json_encode( $body ),
		] );

		return self::normalize_response( $response );
	}

	/**
	 * @return array{ok: bool, status: int, body: array, error: string}
	 */
	private static function normalize_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return [
				'ok'     => false,
				'status' => 0,
				'body'   => [],
				'error'  => $response->get_error_message(),
			];
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$body   = json_decode( $raw, true );
		if ( ! is_array( $body ) ) {
			$body = [ '_raw' => $raw ];
		}

		return [
			'ok'     => $status >= 200 && $status < 300,
			'status' => $status,
			'body'   => $body,
			'error'  => $status >= 200 && $status < 300 ? '' : ( $body['error'] ?? "HTTP {$status}" ),
		];
	}
}
