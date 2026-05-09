<?php

namespace Zaplane\Framework\Cloud;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drives the pairing handshake from the plugin side.
 *
 * Customer pastes a pairing code + cloud URL. We POST to the cloud's
 * `/api/sites/pair` and persist the returned site_id + site_secret in
 * encrypted Bridge state.
 */
class Pairing {

	/**
	 * @return array{ok: bool, error?: string, summary?: array}
	 */
	public static function pair( string $cloud_url, string $code ): array {
		$cloud_url = rtrim( $cloud_url, '/' );

		if ( '' === $cloud_url || ! filter_var( $cloud_url, FILTER_VALIDATE_URL ) ) {
			return [ 'ok' => false, 'error' => __( 'Invalid cloud URL.', 'zaplane' ) ];
		}
		if ( '' === $code ) {
			return [ 'ok' => false, 'error' => __( 'Pairing code is required.', 'zaplane' ) ];
		}

		$endpoint = $cloud_url . '/api/sites/pair';
		$payload  = [
			'code'           => strtoupper( trim( $code ) ),
			'site_url'       => home_url( '/' ),
			'name'           => get_bloginfo( 'name' ),
			'plugin_version' => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : 'unknown',
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
		];

		$res = HttpClient::post_unsigned( $endpoint, $payload, 20 );

		if ( ! $res['ok'] ) {
			$msg = $res['error'] ?: __( 'Pairing failed.', 'zaplane' );
			if ( ! empty( $res['body']['errors']['code'][0] ) ) {
				$msg = $res['body']['errors']['code'][0];
			}
			return [ 'ok' => false, 'error' => $msg ];
		}

		$body = $res['body'];
		if ( empty( $body['site_id'] ) || empty( $body['site_secret'] ) ) {
			return [ 'ok' => false, 'error' => __( 'Pairing response missing site credentials.', 'zaplane' ) ];
		}

		Bridge::save_state( [
			'cloud_url'          => $cloud_url,
			'site_id'            => (int) $body['site_id'],
			'site_secret'        => (string) $body['site_secret'],
			'ingest_endpoint'    => (string) ( $body['ingest_endpoint'] ?? '' ),
			'heartbeat_endpoint' => (string) ( $body['heartbeat_endpoint'] ?? '' ),
			'team_id'            => (int) ( $body['team']['id'] ?? 0 ),
			'team_name'          => (string) ( $body['team']['name'] ?? '' ),
			'paired_at'          => time(),
		] );

		Heartbeat::schedule();

		return [ 'ok' => true, 'summary' => Bridge::public_summary() ];
	}

	public static function disconnect(): void {
		Heartbeat::unschedule();
		Bridge::clear();
		delete_option( 'zaplane_cloud_heartbeat_last' );
	}

	/**
	 * Manual heartbeat, useful for "Test connection" buttons in the UI.
	 *
	 * @return array{ok: bool, error?: string, body?: array}
	 */
	public static function heartbeat(): array {
		$state = Bridge::get_state();
		if ( empty( $state ) ) {
			return [ 'ok' => false, 'error' => 'not_paired' ];
		}

		$res = HttpClient::post_signed(
			$state['heartbeat_endpoint'],
			[
				'plugin_version' => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : 'unknown',
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
			],
			(int) $state['site_id'],
			(string) $state['site_secret']
		);

		return [ 'ok' => $res['ok'], 'error' => $res['error'], 'body' => $res['body'] ];
	}
}
