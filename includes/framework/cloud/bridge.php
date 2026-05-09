<?php

namespace Zaplane\Framework\Cloud;

use Zaplane\Framework\Classes\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the site's connection to Zaplane Cloud (cloud URL, site_id,
 * site_secret, endpoints) and tells the rest of the plugin whether
 * we're currently paired.
 *
 * The full payload is encrypted at rest via the existing Encryption helper
 * so a DB dump alone can't be replayed against the cloud.
 */
class Bridge {

	private const OPTION = 'zaplane_cloud_connection';

	public static function is_paired(): bool {
		$state = self::get_state();
		return ! empty( $state['site_id'] ) && ! empty( $state['site_secret'] );
	}

	/**
	 * Returns the decrypted connection state, or [] if not paired.
	 *
	 * @return array{
	 *   cloud_url: string,
	 *   site_id: int,
	 *   site_secret: string,
	 *   ingest_endpoint: string,
	 *   heartbeat_endpoint: string,
	 *   team_id: int,
	 *   team_name: string,
	 *   paired_at: int
	 * }|array{}
	 */
	public static function get_state(): array {
		$raw = get_option( self::OPTION, '' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}
		try {
			$data = Encryption::decrypt( $raw );
			return is_array( $data ) ? $data : [];
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	public static function save_state( array $state ): void {
		update_option( self::OPTION, Encryption::encrypt( $state ), false );
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Public summary safe to expose to the admin UI — never includes
	 * the site_secret.
	 */
	public static function public_summary(): array {
		$state = self::get_state();
		if ( empty( $state ) ) {
			return [
				'paired' => false,
			];
		}
		return [
			'paired'             => true,
			'cloud_url'          => $state['cloud_url'] ?? '',
			'site_id'            => (int) ( $state['site_id'] ?? 0 ),
			'team_id'            => (int) ( $state['team_id'] ?? 0 ),
			'team_name'          => $state['team_name'] ?? '',
			'paired_at'          => (int) ( $state['paired_at'] ?? 0 ),
			'ingest_endpoint'    => $state['ingest_endpoint'] ?? '',
			'heartbeat_endpoint' => $state['heartbeat_endpoint'] ?? '',
		];
	}
}
