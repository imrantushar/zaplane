<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Core\IntegrationLoader;
use Zaplane\Exceptions\ConnectionException;
use Zaplane\Exceptions\EncryptionException;
use Zaplane\Exceptions\IntegrationException;

class ConnectionManager {

	private string $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_connections';
	}

	public function create( int $user_id, string $app, string $name, string $auth_type, array $credentials ) {
		global $wpdb;

		$integration = IntegrationLoader::get( $app );
		if ( ! $integration ) {
			throw IntegrationException::notFound( $app );
		}

		try {
			$encrypted = Encryption::encrypt( $credentials );
		} catch ( EncryptionException $e ) {
			throw ConnectionException::createFailed( $app, $e->getMessage() );
		}

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'               => $user_id,
				'app'                   => $app,
				'name'                  => $name,
				'auth_type'             => $auth_type,
				'encrypted_credentials' => $encrypted,
				'status'                => 'active',
				'created_at'            => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			throw ConnectionException::createFailed( $app, 'Database insert failed' );
		}

		return (int) $wpdb->insert_id;
	}

	public function get( int $id, bool $decrypt = false ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( $decrypt && ! empty( $row['encrypted_credentials'] ) ) {
			try {
				$row['credentials'] = Encryption::decrypt( $row['encrypted_credentials'] );
			} catch ( EncryptionException $e ) {
				$row['credentials'] = array();
				$row['decrypt_error'] = $e->getMessage();
			}
		}

		unset( $row['encrypted_credentials'] );

		return $row;
	}

	public function get_user_connections( int $user_id, ?string $app = null ): array {
		global $wpdb;

		$sql = "SELECT id, user_id, app, name, auth_type, status,
                       last_used_at, last_tested_at, last_test_status, created_at
                FROM {$this->table_name}
                WHERE user_id = %d";

		$params = array( $user_id );

		if ( $app !== null ) {
			$sql .= ' AND app = %s';
			$params[] = $app;
		}

		$sql .= ' ORDER BY created_at DESC';

		return $wpdb->get_results(
			$wpdb->prepare( $sql, ...$params ),
			ARRAY_A
		) ?: array();
	}

	public function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed_fields = array( 'name', 'status' );
		$update_data = array_intersect_key( $data, array_flip( $allowed_fields ) );

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $id ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	public function update_credentials( int $id, array $credentials ): bool {
		global $wpdb;

		try {
			$encrypted = Encryption::encrypt( $credentials );
		} catch ( EncryptionException $e ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table_name,
			array( 'encrypted_credentials' => $encrypted ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		return $result !== false;
	}

	public function user_owns_connection( int $connection_id, int $user_id ): bool {
		global $wpdb;

		$owner_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$this->table_name} WHERE id = %d",
				$connection_id
			)
		);

		return (int) $owner_id === $user_id;
	}

	public function test( int $id ): array {
		global $wpdb;

		$connection = $this->get( $id, true );

		if ( ! $connection ) {
			throw ConnectionException::notFound( $id );
		}

		if ( isset( $connection['decrypt_error'] ) ) {
			throw ConnectionException::testFailed( $id, 'Failed to decrypt credentials: ' . $connection['decrypt_error'] );
		}

		IntegrationLoader::init();
		$integration = IntegrationLoader::get( $connection['app'] );

		if ( ! $integration ) {
			throw IntegrationException::notFound( $connection['app'] );
		}

		$result = $integration::test_connection( $connection['credentials'] ?? array() );

		$wpdb->update(
			$this->table_name,
			array(
				'last_tested_at'   => current_time( 'mysql' ),
				'last_test_status' => $result['success'] ? 'success' : 'failed',
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $result;
	}

	public function get_execution_credentials( int $id ): array {
		global $wpdb;

		$connection = $this->get( $id, true );

		if ( ! $connection ) {
			throw ConnectionException::notFound( $id );
		}

		if ( isset( $connection['decrypt_error'] ) ) {
			throw EncryptionException::decryptionFailed( $connection['decrypt_error'] );
		}

		$credentials = $connection['credentials'] ?? array();

		if ( $connection['auth_type'] === 'oauth2' ) {
			$credentials = $this->refresh_oauth_if_needed( $id, $connection, $credentials );
		}

		$wpdb->update(
			$this->table_name,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return $credentials;
	}

	private function refresh_oauth_if_needed( int $id, array $connection, array $credentials ): array {
		global $wpdb;

		$expires_at = $connection['oauth_expires_at'] ?? null;

		if ( ! $expires_at ) {
			return $credentials;
		}

		$expires_timestamp = strtotime( $expires_at );
		$buffer = 5 * MINUTE_IN_SECONDS;

		if ( $expires_timestamp > ( time() + $buffer ) ) {
			return $credentials;
		}

		$refresh_token = $credentials['refresh_token'] ?? null;

		if ( ! $refresh_token ) {
			return $credentials;
		}

		IntegrationLoader::init();
		$integration = IntegrationLoader::get( $connection['app'] );

		if ( ! $integration ) {
			return $credentials;
		}

		try {
			$new_tokens = $integration::refresh_oauth_token( $refresh_token );

			$credentials = array_merge( $credentials, $new_tokens );

			$this->update_credentials( $id, $credentials );

			if ( isset( $new_tokens['expires_in'] ) ) {
				$new_expiry = gmdate( 'Y-m-d H:i:s', time() + (int) $new_tokens['expires_in'] );
				$wpdb->update(
					$this->table_name,
					array( 'oauth_expires_at' => $new_expiry ),
					array( 'id' => $id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		} catch ( \Throwable $e ) {
			error_log( 'Zaplane OAuth refresh failed for connection ' . $id . ': ' . $e->getMessage() );
		}

		return $credentials;
	}

	public function set_oauth_expiry( int $id, int $expires_in ): bool {
		global $wpdb;

		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $expires_in );

		$result = $wpdb->update(
			$this->table_name,
			array( 'oauth_expires_at' => $expires_at ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}
}
