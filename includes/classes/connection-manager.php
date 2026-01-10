<?php
namespace Zaplane\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Core\IntegrationLoader;

/**
 * Connection Manager Service
 * Handles CRUD operations for integration connections
 */
class ConnectionManager {

	private string $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_connections';
	}

	/**
	 * Create a new connection
	 *
	 * @param int    $user_id     WordPress user ID
	 * @param string $app         Integration slug (e.g., 'slack', 'gmail')
	 * @param string $name        User-defined connection name
	 * @param string $auth_type   'api_key' | 'oauth2' | 'basic'
	 * @param array  $credentials Plain credentials to encrypt
	 */
	public function create( int $user_id, string $app, string $name, string $auth_type, array $credentials ) {
		global $wpdb;

		// Validate integration exists
		$integration = IntegrationLoader::get( $app );
		if ( ! $integration ) {
			return new \WP_Error( 'invalid_app', 'Integration not found: ' . $app );
		}

		// Encrypt credentials
		try {
			$encrypted = Encryption::encrypt( $credentials );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'encryption_failed', $e->getMessage() );
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
			return new \WP_Error( 'db_error', 'Failed to create connection' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a single connection by ID
	 *
	 * @param int  $id      Connection ID
	 * @param bool $decrypt Whether to decrypt credentials
	 * @return array|null Connection data or null
	 */
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
			} catch ( \Exception $e ) {
				$row['credentials'] = array();
				$row['decrypt_error'] = $e->getMessage();
			}
		}

		// Never expose encrypted_credentials in output
		unset( $row['encrypted_credentials'] );

		return $row;
	}

	/**
	 * Get all connections for a user
	 *
	 * @param int         $user_id WordPress user ID
	 * @param string|null $app     Optional: filter by integration
	 * @return array List of connections (credentials excluded)
	 */
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

	/**
	 * Update connection metadata (not credentials)
	 *
	 * @param int   $id   Connection ID
	 * @param array $data Fields to update (name, status)
	 * @return bool Success
	 */
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

	/**
	 * Update connection credentials (re-encrypts)
	 *
	 * @param int   $id          Connection ID
	 * @param array $credentials New credentials
	 * @return bool Success
	 */
	public function update_credentials( int $id, array $credentials ): bool {
		global $wpdb;

		try {
			$encrypted = Encryption::encrypt( $credentials );
		} catch ( \Exception $e ) {
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

	/**
	 * Delete a connection
	 *
	 * @param int $id Connection ID
	 * @return bool Success
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Verify user owns connection
	 *
	 * @param int $connection_id Connection ID
	 * @param int $user_id       WordPress user ID
	 * @return bool
	 */
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

	/**
	 * Test a connection
	 *
	 * @param int $id Connection ID
	 * @return array Test result ['success', 'message', 'details']
	 */
	public function test( int $id ): array {
		global $wpdb;

		$connection = $this->get( $id, true );

		if ( ! $connection ) {
			return array(
				'success' => false,
				'message' => 'Connection not found',
				'details' => array(),
			);
		}

		if ( isset( $connection['decrypt_error'] ) ) {
			return array(
				'success' => false,
				'message' => 'Failed to decrypt credentials: ' . $connection['decrypt_error'],
				'details' => array(),
			);
		}

		$integration = IntegrationLoader::get( $connection['app'] );

		if ( ! $integration ) {
			return array(
				'success' => false,
				'message' => 'Integration not found: ' . $connection['app'],
				'details' => array(),
			);
		}

		// Call integration's test_connection method
		$result = $integration::test_connection( $connection['credentials'] ?? array() );

		// Update test status
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

	/**
	 * Get credentials for workflow execution
	 * Handles OAuth token refresh automatically
	 *
	 * @param int $id Connection ID
	 * @return array Decrypted, valid credentials
	 * @throws \Exception on failure
	 */
	public function get_execution_credentials( int $id ): array {
		global $wpdb;

		$connection = $this->get( $id, true );

		if ( ! $connection ) {
			throw new \Exception( 'Connection not found' );
		}

		if ( isset( $connection['decrypt_error'] ) ) {
			throw new \Exception( 'Failed to decrypt credentials' );
		}

		$credentials = $connection['credentials'] ?? array();

		// Check if OAuth token needs refresh
		if ( $connection['auth_type'] === 'oauth2' ) {
			$credentials = $this->refresh_oauth_if_needed( $id, $connection, $credentials );
		}

		// Update last used timestamp
		$wpdb->update(
			$this->table_name,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return $credentials;
	}

	/**
	 * Refresh OAuth token if expired
	 *
	 * @param int   $id          Connection ID
	 * @param array $connection  Connection data
	 * @param array $credentials Current credentials
	 * @return array Updated credentials
	 */
	private function refresh_oauth_if_needed( int $id, array $connection, array $credentials ): array {
		global $wpdb;

		// Check if token has expires_at and is expired
		$expires_at = $connection['oauth_expires_at'] ?? null;

		if ( ! $expires_at ) {
			return $credentials;
		}

		$expires_timestamp = strtotime( $expires_at );
		$buffer = 5 * MINUTE_IN_SECONDS; // Refresh 5 minutes before expiry

		if ( $expires_timestamp > ( time() + $buffer ) ) {
			return $credentials; // Token still valid
		}

		// Token expired or expiring soon - refresh it
		$refresh_token = $credentials['refresh_token'] ?? null;

		if ( ! $refresh_token ) {
			return $credentials; // No refresh token available
		}

		$integration = IntegrationLoader::get( $connection['app'] );

		if ( ! $integration ) {
			return $credentials;
		}

		try {
			$new_tokens = $integration::refresh_oauth_token( $refresh_token );

			// Merge new tokens with existing credentials
			$credentials = array_merge( $credentials, $new_tokens );

			// Update stored credentials
			$this->update_credentials( $id, $credentials );

			// Update expiry time if provided
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
		} catch ( \Exception $e ) {
			// Log refresh failure but return existing credentials
			error_log( 'Zaplane OAuth refresh failed for connection ' . $id . ': ' . $e->getMessage() );
		}

		return $credentials;
	}

	/**
	 * Set OAuth expiry time for a connection
	 *
	 * @param int $id         Connection ID
	 * @param int $expires_in Seconds until expiry
	 * @return bool Success
	 */
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
