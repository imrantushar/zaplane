<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Exceptions\ConnectionException;
use Zaplane\Framework\Exceptions\EncryptionException;
use Zaplane\Framework\Exceptions\IntegrationException;
use Zaplane\Models\Connection;

class ConnectionManager {



	public function create( int $user_id, string $app, string $name, string $auth_type, array $credentials, ?string $icon = null ): array {
		$integration = IntegrationLoader::get( $app );
		if ( ! $integration ) {
			throw IntegrationException::notFound( esc_html( $app ) );
		}

		$test_result = null;
		if ( 'oauth2' !== $auth_type ) {
			$class = get_class( $integration );
			$test_result = $class::test_connection( $credentials );
			if ( ! ( $test_result['success'] ?? false ) ) {
				throw ConnectionException::invalidCredentials(
					esc_html( $app ),
					esc_html( $test_result['message'] ?? 'Connection test failed' )
				);
			}
		}

		try {
			$encrypted = Encryption::encrypt( $credentials );
		} catch ( EncryptionException $e ) {
			throw ConnectionException::createFailed( esc_html( $app ), esc_html( $e->getMessage() ) );
		}

		$connection = Connection::create([
			'user_id' => $user_id,
			'app' => $app,
			'icon' => $icon,
			'name' => $name,
			'auth_type' => $auth_type,
			'encrypted_credentials' => $encrypted,
			'status' => 'active',
		]);

		if ( null !== $test_result ) {
			$connection->markAsTested( true );
		}

		return [
			'id' => $connection->id,
			'test_result' => $test_result
		];
	}

	public function get( int $id, bool $decrypt = false ): ?array {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			return null;
		}

		$data = $connection->toArray();

		if ( $decrypt && ! empty( $connection->encrypted_credentials ) ) {
			try {
				$data['credentials'] = Encryption::decrypt( $connection->encrypted_credentials );
			} catch ( EncryptionException $e ) {
				$data['credentials'] = [];
				$data['decrypt_error'] = $e->getMessage();
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \Exception $e ) {
				// Silently ignore non-encryption exceptions for credential decryption.
			}
		}

		return $data;
	}

	/**
	 * @param array{status?:string,search?:string} $filters
	 */
	public function get_user_connections( int $user_id, ?string $app = null, int $page = 1, int $perPage = 20, array $filters = [] ): array {
		$status = in_array( $filters['status'] ?? '', [ 'active', 'inactive' ], true ) ? $filters['status'] : '';
		$search = trim( (string) ( $filters['search'] ?? '' ) );

		// A fresh query each time: count() rewrites the one it runs on.
		$base = static function ( bool $with_status = true ) use ( $user_id, $app, $status, $search ) {
			$q = Connection::where( 'user_id', $user_id );
			if ( null !== $app && '' !== $app ) {
				$q->where( 'app', $app );
			}
			if ( '' !== $search ) {
				global $wpdb;
				$like = '%' . $wpdb->esc_like( $search ) . '%';
				$q->whereRaw( '(name LIKE %s OR app LIKE %s)', [ $like, $like ] );
			}
			if ( $with_status && '' !== $status ) {
				$q->where( 'status', $status );
			}
			return $q;
		};
		$query = $base();

		$total = $base()->count();

		$connections = $query->orderBy( 'name', 'asc' )
			->forPage( $page, $perPage )
			->get();

		// For the list's filters: per-status totals (other filters applied) and
		// the apps this user has connections for.
		$counts = [ 'all' => $base( false )->count() ];
		foreach ( [ 'active', 'inactive' ] as $one ) {
			$counts[ $one ] = $base( false )->where( 'status', $one )->count();
		}
		$apps = [];
		foreach ( Connection::where( 'user_id', $user_id )->get() as $row ) {
			$apps[ (string) $row->app ] = true;
		}

		return [
			'data' => $connections->toArray(),
			'counts' => $counts,
			'apps' => array_keys( $apps ),
			'pagination' => [
				'page' => $page,
				'per_page' => $perPage,
				'total' => $total,
				'total_pages' => (int) ceil( $total / $perPage ),
			],
		];
	}

	public function update( int $id, array $data ): bool {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			return false;
		}

		$allowed_fields = [ 'name', 'status' ];
		$update_data = array_intersect_key( $data, array_flip( $allowed_fields ) );

		if ( empty( $update_data ) ) {
			return false;
		}

		foreach ( $update_data as $key => $value ) {
			$connection->{$key} = $value;
		}

		return $connection->save();
	}

	public function update_credentials( int $id, array $credentials ): array {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			throw ConnectionException::notFound( $id );
		}

		$test_result = null;
		if ( 'oauth2' !== $connection->auth_type && IntegrationLoader::has( $connection->app ) ) {
			$integration = IntegrationLoader::get( $connection->app );
			$class       = get_class( $integration );
			$test_result = $class::test_connection( $credentials );
			if ( ! ( $test_result['success'] ?? false ) ) {
				throw ConnectionException::invalidCredentials(
					esc_html( $connection->app ),
					esc_html( $test_result['message'] ?? 'Connection test failed' )
				);
			}
		}

		try {
			$encrypted = Encryption::encrypt( $credentials );
		} catch ( EncryptionException $e ) {
			throw ConnectionException::createFailed( esc_html( $connection->app ), esc_html( $e->getMessage() ) );
		}

		$connection->encrypted_credentials = $encrypted;
		$saved = $connection->save();

		if ( null !== $test_result ) {
			$connection->markAsTested( true );
		}

		return [
			'saved'       => $saved,
			'test_result' => $test_result,
		];
	}

	public function delete( int $id ): bool {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			return false;
		}

		return $connection->delete();
	}

	public function user_owns_connection( int $connection_id, int $user_id ): bool {
		$connection = Connection::find( $connection_id );

		if ( ! $connection ) {
			return false;
		}

		return $connection->isOwnedBy( $user_id );
	}

	public function test( int $id ): array {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			throw ConnectionException::notFound( $id );
		}

		$credentials = $connection->getCredentials();

		if ( empty( $credentials ) && ! empty( $connection->encrypted_credentials ) ) {
			throw ConnectionException::testFailed( esc_html( (string) $id ), 'Failed to decrypt credentials' );
		}

		if ( ! IntegrationLoader::has( $connection->app ) ) {
			throw IntegrationException::notFound( esc_html( $connection->app ) );
		}

		$integration = IntegrationLoader::get( $connection->app );
		$class = get_class( $integration );

		$result = $class::test_connection( $credentials );

		$connection->markAsTested( $result['success'] ?? false );

		return $result;
	}

	public function get_execution_credentials( int $id ): array {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			throw ConnectionException::notFound( $id );
		}

		$credentials = $connection->getCredentials();

		if ( empty( $credentials ) && ! empty( $connection->encrypted_credentials ) ) {
			throw EncryptionException::decryptionFailed( 'Failed to decrypt credentials' );
		}

		if ( 'oauth2' === $connection->auth_type ) {
			$credentials = $this->refresh_oauth_if_needed( $connection, $credentials );
		}

		$connection->markAsUsed();

		return $credentials;
	}

	private function refresh_oauth_if_needed( Connection $connection, array $credentials ): array {
		if ( ! $connection->oauth_expires_at ) {
			return $credentials;
		}

		if ( ! $connection->isOAuthExpiringSoon( 5 ) ) {
			return $credentials;
		}

		$refresh_token = $credentials['refresh_token'] ?? null;

		if ( ! $refresh_token ) {
			return $credentials;
		}

		if ( ! IntegrationLoader::has( $connection->app ) ) {
			return $credentials;
		}

		$integration = IntegrationLoader::get( $connection->app );
		$class = get_class( $integration );

		try {
			$new_tokens = $class::refresh_oauth_token( $credentials );
			$credentials = array_merge( $credentials, $new_tokens );

			$connection->setCredentials( $credentials );

			if ( isset( $new_tokens['expires_in'] ) ) {
				$connection->setOAuthExpiry( (int) $new_tokens['expires_in'] );
			}
		} catch ( \Throwable $e ) {
			$e->getMessage();
		}

		return $credentials;
	}

	public function set_oauth_expiry( int $id, int $expires_in ): bool {
		$connection = Connection::find( $id );

		if ( ! $connection ) {
			return false;
		}

		return $connection->setOAuthExpiry( $expires_in );
	}
}
