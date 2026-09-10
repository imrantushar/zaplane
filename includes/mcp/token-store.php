<?php

namespace Zaplane\Mcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-client bearer tokens for the MCP endpoint, each carrying its own scopes.
 *
 * The endpoint's whole power comes from whoever holds the token, and one of its
 * tools spends money and sends mail. A single shared secret that can do
 * everything is the wrong shape for that, so a token is issued per client, is
 * scoped, records who issued it, and can be revoked on its own.
 *
 * The secret is shown once at issue time and only its SHA-256 lives in the
 * option, so a database read can't be replayed against the endpoint.
 *
 * Presented form is `zpl_<id>.<secret>` — the id selects the record, the secret
 * is compared in constant time.
 */
class TokenStore {

	private const OPTION        = 'zaplane_mcp_tokens';
	private const LEGACY_OPTION = 'zaplane_mcp_token';

	public const SCOPE_READ  = 'read';
	public const SCOPE_WRITE = 'write';
	public const SCOPE_RUN   = 'run';

	/** Issued by default: enough to explore and build, not to fire side effects. */
	public const DEFAULT_SCOPES = [ self::SCOPE_READ, self::SCOPE_WRITE ];

	public const ALL_SCOPES = [ self::SCOPE_READ, self::SCOPE_WRITE, self::SCOPE_RUN ];

	/**
	 * Every stored record, without secrets.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		$out = [];

		foreach ( self::records() as $record ) {
			$out[] = [
				'id'           => (string) $record['id'],
				'name'         => (string) $record['name'],
				'scopes'       => array_values( (array) $record['scopes'] ),
				'user_id'      => (int) $record['user_id'],
				'created_at'   => (string) $record['created_at'],
				'last_used_at' => $record['last_used_at'] ?? null,
				'client_id'    => (string) ( $record['client_id'] ?? '' ),
				'expires_at'   => (int) ( $record['expires_at'] ?? 0 ),
				'workflows'    => array_values( (array) ( $record['workflows'] ?? [] ) ),
			];
		}

		return $out;
	}

	/**
	 * Issue a token. The plaintext secret is returned here and nowhere else —
	 * it is not recoverable afterwards.
	 *
	 * @param array<int,string> $scopes
	 * @return array<string,mixed>
	 */
	public static function issue( string $name, array $scopes = self::DEFAULT_SCOPES, int $user_id = 0, array $opts = [] ): array {
		$name = sanitize_text_field( $name );
		if ( '' === trim( $name ) ) {
			$name = 'MCP client';
		}

		$scopes = self::sanitize_scopes( $scopes );
		if ( empty( $scopes ) ) {
			$scopes = self::DEFAULT_SCOPES;
		}

		$id     = strtolower( wp_generate_password( 12, false ) );
		$secret = wp_generate_password( 48, false );

		// An OAuth-issued token belongs to a registered client and expires; one
		// created by hand in the settings screen does neither.
		$client_id  = isset( $opts['client_id'] ) ? (string) $opts['client_id'] : '';
		$expires_in = isset( $opts['expires_in'] ) ? (int) $opts['expires_in'] : 0;

		// Which workflows `run` may start. Empty means every one of them, which is
		// what a token issued before this existed has, and what "any" still means.
		$workflows = self::sanitize_workflows( (array) ( $opts['workflows'] ?? [] ) );

		$refresh = '';
		if ( ! empty( $opts['with_refresh'] ) ) {
			$refresh = wp_generate_password( 48, false );
		}

		$records   = self::records();
		$records[] = [
			'id'           => $id,
			'name'         => $name,
			'hash'         => hash( 'sha256', $secret ),
			'scopes'       => $scopes,
			'user_id'      => $user_id ?: get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
			'last_used_at' => null,
			'client_id'    => $client_id,
			'expires_at'   => $expires_in > 0 ? time() + $expires_in : 0,
			'refresh_hash' => '' !== $refresh ? hash( 'sha256', $refresh ) : '',
			'workflows'    => $workflows,
		];

		self::persist( $records );

		return [
			'id'            => $id,
			'name'          => $name,
			'scopes'        => $scopes,
			'workflows'     => $workflows,
			'token'         => 'zpl_' . $id . '.' . $secret,
			'refresh_token' => '' !== $refresh ? 'zpr_' . $id . '.' . $refresh : '',
			'expires_in'    => $expires_in,
			'user_id'       => (int) end( $records )['user_id'],
		];
	}

	/**
	 * Revoke every token issued to one registered client, and say how many.
	 *
	 * Removing a client without this would leave its tokens working while the
	 * registration they came from no longer exists — access nobody can see the
	 * origin of.
	 *
	 * @param string $client_id The registered client.
	 */
	public static function revoke_for_client( string $client_id ): int {
		$records = self::records();
		$kept    = array_values( array_filter( $records, fn( $r ) => (string) ( $r['client_id'] ?? '' ) !== $client_id ) );
		$removed = count( $records ) - count( $kept );

		if ( $removed > 0 ) {
			self::persist( $kept );
		}

		return $removed;
	}

	/** Revoke one token by id. Returns false when nothing matched. */
	public static function revoke( string $id ): bool {
		$records = self::records();
		$kept    = array_values( array_filter( $records, fn( $r ) => $r['id'] !== $id ) );

		if ( count( $kept ) === count( $records ) ) {
			return false;
		}

		self::persist( $kept );

		return true;
	}

	/**
	 * Resolve a presented bearer token to its record, or null.
	 *
	 * Touches last_used_at on a hit so a stale token is visible in the UI.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function resolve( string $presented ): ?array {
		$presented = trim( $presented );
		if ( '' === $presented ) {
			return null;
		}

		$records = self::records();

		if ( 0 === strpos( $presented, 'zpl_' ) && false !== strpos( $presented, '.' ) ) {
			[ $id, $secret ] = explode( '.', substr( $presented, 4 ), 2 );

			foreach ( $records as $index => $record ) {
				if ( $record['id'] !== $id ) {
					continue;
				}
				if ( ! hash_equals( (string) $record['hash'], hash( 'sha256', $secret ) ) ) {
					return null;
				}

				// An OAuth access token is short-lived; the client is expected to
				// present its refresh token once this lapses.
				if ( ! empty( $record['expires_at'] ) && $record['expires_at'] <= time() ) {
					return null;
				}

				self::touch( $index );

				return $record;
			}

			return null;
		}

		// A token issued before scoping existed. Honoured so an already-connected
		// client keeps working, with every scope, until it is rotated.
		$legacy = (string) get_option( self::LEGACY_OPTION, '' );
		if ( '' !== $legacy && hash_equals( $legacy, $presented ) ) {
			return [
				'id'      => 'legacy',
				'name'    => 'Legacy token',
				'scopes'  => self::ALL_SCOPES,
				'user_id' => 0,
				'legacy'  => true,
			];
		}

		return null;
	}

	/**
	 * Trade a refresh token for the record it belongs to.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function resolve_refresh( string $presented ): ?array {
		$presented = trim( $presented );
		if ( 0 !== strpos( $presented, 'zpr_' ) || false === strpos( $presented, '.' ) ) {
			return null;
		}

		[ $id, $secret ] = explode( '.', substr( $presented, 4 ), 2 );

		foreach ( self::records() as $record ) {
			if ( $record['id'] !== $id || '' === (string) $record['refresh_hash'] ) {
				continue;
			}

			return hash_equals( (string) $record['refresh_hash'], hash( 'sha256', $secret ) )
				? $record
				: null;
		}

		return null;
	}

	/**
	 * Whether this token may start a particular workflow.
	 *
	 * `run` is the scope that spends money and sends mail, and until now holding
	 * it meant holding it over every workflow on the site. A token can now name
	 * the ones it is for; naming none keeps the old meaning, so nothing issued
	 * before this narrows underneath anyone.
	 *
	 * @param array<string,mixed> $record      The resolved token.
	 * @param int                 $workflow_id The workflow being asked for.
	 */
	public static function may_run( array $record, int $workflow_id ): bool {
		$allowed = self::sanitize_workflows( (array) ( $record['workflows'] ?? [] ) );

		return empty( $allowed ) || in_array( $workflow_id, $allowed, true );
	}

	/**
	 * @param array<int,mixed> $ids
	 * @return array<int,int>
	 */
	public static function sanitize_workflows( array $ids ): array {
		$clean = array_values( array_unique( array_filter( array_map( 'intval', $ids ), fn( $id ) => $id > 0 ) ) );
		sort( $clean );

		return $clean;
	}

	/**
	 * @param array<string,mixed> $record
	 */
	public static function has_scope( array $record, string $scope ): bool {
		return in_array( $scope, (array) ( $record['scopes'] ?? [] ), true );
	}

	/** True once at least one token exists (legacy included). */
	public static function has_any(): bool {
		return ! empty( self::records() ) || '' !== (string) get_option( self::LEGACY_OPTION, '' );
	}

	/**
	 * @param array<int,string> $scopes
	 * @return array<int,string>
	 */
	public static function sanitize_scopes( array $scopes ): array {
		$clean = [];

		foreach ( $scopes as $scope ) {
			$scope = is_string( $scope ) ? strtolower( trim( $scope ) ) : '';
			if ( in_array( $scope, self::ALL_SCOPES, true ) && ! in_array( $scope, $clean, true ) ) {
				$clean[] = $scope;
			}
		}

		// Writing implies being able to read back what you wrote.
		if ( in_array( self::SCOPE_WRITE, $clean, true ) && ! in_array( self::SCOPE_READ, $clean, true ) ) {
			$clean[] = self::SCOPE_READ;
		}

		return $clean;
	}

	/* --------------------------------------------------------------------- */

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function records(): array {
		$raw = get_option( self::OPTION, [] );

		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $record ) {
			if ( ! is_array( $record ) || empty( $record['id'] ) || empty( $record['hash'] ) ) {
				continue;
			}

			$out[] = [
				'id'           => (string) $record['id'],
				'name'         => (string) ( $record['name'] ?? 'MCP client' ),
				'hash'         => (string) $record['hash'],
				'scopes'       => self::sanitize_scopes( (array) ( $record['scopes'] ?? [] ) ),
				'user_id'      => (int) ( $record['user_id'] ?? 0 ),
				'created_at'   => (string) ( $record['created_at'] ?? '' ),
				'last_used_at' => $record['last_used_at'] ?? null,
				'client_id'    => (string) ( $record['client_id'] ?? '' ),
				'expires_at'   => (int) ( $record['expires_at'] ?? 0 ),
				'workflows'    => array_values( (array) ( $record['workflows'] ?? [] ) ),
				'refresh_hash' => (string) ( $record['refresh_hash'] ?? '' ),
			];
		}

		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $records
	 */
	private static function persist( array $records ): void {
		update_option( self::OPTION, array_values( $records ), false );
	}

	private static function touch( int $index ): void {
		$records = self::records();

		if ( ! isset( $records[ $index ] ) ) {
			return;
		}

		$records[ $index ]['last_used_at'] = current_time( 'mysql' );

		self::persist( $records );
	}
}
