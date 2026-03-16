<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Traits\ActionResponseTrait;

trait UserActionsTrait {

	private static function get_user_id_from_config( array $config ): int {
		return (int) ( $config['user_id'] ?? 0 );
	}

	private static function require_user_from_config( array $config, string &$error = '' ) {
		$user_id = static::get_user_id_from_config( $config );
		if ( ! $user_id ) {
			$error = 'User ID is required';
			return null;
		}

		$user = static::find_user( 'id', $user_id );
		if ( ! $user ) {
			$error = 'User not found';
			return null;
		}

		return $user;
	}

	private static function find_user( string $field, $value ) {
		$user = get_user_by( $field, $value );
		return $user instanceof \WP_User ? $user : null;
	}

	private static function format_user_response( \WP_User $user ): array {
		$payload = static::query_users( [ 'users' => [ $user ] ] );
		return [ 'user' => $payload[0] ?? [] ];
	}

	protected static function action_create_user( array $config ): array {
		$user_id = wp_insert_user( $config );

		if ( is_wp_error( $user_id ) ) {
			return static::error( $user_id->get_error_message() );
		}

		return static::success( [ 'user_id' => $user_id ] );
	}

	protected static function action_update_user( array $config ): array {
		$config['ID'] = $config['user_id'] ?? 0;
		$updated = wp_update_user( $config );

		if ( is_wp_error( $updated ) ) {
			return static::error( $updated->get_error_message() );
		}

		return static::success( [ 'updated' => $updated ] );
	}

	protected static function action_delete_user( array $config ): array {
		$deleted = wp_delete_user( $config['user_id'], $config['reassign'] ?? null );

		if ( ! $deleted ) {
			return static::error( "Failed to delete user ID {$config['user_id']}" );
		}

		return static::success( [ 'deleted_user_id' => $config['user_id'] ] );
	}

	protected static function action_get_users( array $config ): array {
		$users = get_users( $config );
		return static::success([
			'users' => static::query_users( [ 'users' => $users ] )
		]);
	}

	protected static function action_get_users_by_role( array $config ): array {

		return static::action_get_users( $config );
	}

	protected static function action_get_user_by_id( array $config ): array {
		$error = '';
		$user = static::require_user_from_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}

		return static::success( static::format_user_response( $user ) );
	}

	protected static function action_get_user_by_email( array $config ): array {
		$email = sanitize_email( (string) ( $config['email'] ?? '' ) );
		if ( '' === $email ) {
			return static::error( 'Email is required' );
		}

		$user = static::find_user( 'email', $email );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}

		return static::success( static::format_user_response( $user ) );
	}

	protected static function action_get_user_by_field( array $config ): array {
		$field = strtolower( (string) ( $config['field'] ?? '' ) );
		$value = $config['value'] ?? '';

		$allowed = [ 'id', 'email', 'login', 'slug' ];
		if ( ! in_array( $field, $allowed, true ) ) {
			return static::error( 'Unsupported field' );
		}
		if ( '' === (string) $value ) {
			return static::error( 'Field value is required' );
		}

		if ( 'id' === $field ) {
			$value = (int) $value;
		}

		$user = static::find_user( $field, $value );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}

		return static::success( static::format_user_response( $user ) );
	}

	protected static function action_get_user_meta_all( array $config ): array {
		$user_id = static::get_user_id_from_config( $config );
		if ( ! $user_id ) {
			return static::error( 'User ID is required' );
		}

		return static::success([
			'user_id' => $user_id,
			'meta' => get_user_meta( $user_id ),
		]);
	}

	protected static function action_get_user_meta_single( array $config ): array {
		$user_id = static::get_user_id_from_config( $config );
		$meta_key = (string) ( $config['meta_key'] ?? '' );
		if ( ! $user_id || '' === $meta_key ) {
			return static::error( 'User ID and meta key are required' );
		}

		return static::success([
			'user_id' => $user_id,
			'meta_key' => $meta_key,
			'meta_value' => get_user_meta( $user_id, $meta_key, true ),
		]);
	}

	protected static function action_update_user_meta( array $config ): array {
		$user_id = static::get_user_id_from_config( $config );
		$meta_key = (string) ( $config['meta_key'] ?? '' );
		if ( ! $user_id || '' === $meta_key ) {
			return static::error( 'User ID and meta key are required' );
		}

		$updated = update_user_meta( $user_id, $meta_key, $config['meta_value'] ?? '' );
		return static::success([
			'user_id' => $user_id,
			'meta_key' => $meta_key,
			'updated' => (bool) $updated,
		]);
	}

	protected static function action_send_password_reset_email( array $config ): array {
		$user_login_or_email = trim( (string) ( $config['user_login_or_email'] ?? '' ) );
		if ( '' === $user_login_or_email ) {
			return static::error( 'Username or email is required' );
		}

		$result = retrieve_password( $user_login_or_email );
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}

		return static::success([
			'sent' => true,
			'user_login_or_email' => $user_login_or_email,
		]);
	}

	protected static function action_authenticate_user( array $config ): array {
		$creds = [
			'user_login'    => (string) ( $config['user_login'] ?? '' ),
			'user_password' => (string) ( $config['user_password'] ?? '' ),
			'remember'      => ! empty( $config['remember'] ),
		];

		if ( '' === $creds['user_login'] || '' === $creds['user_password'] ) {
			return static::error( 'Username/email and password are required' );
		}

		$secure_cookie = ! empty( $config['secure_cookie'] );
		$user = wp_signon( $creds, $secure_cookie );
		if ( is_wp_error( $user ) ) {
			return static::error( $user->get_error_message() );
		}
		return static::success([
			'authenticated' => true,
			'user' => static::format_user_response( $user )['user'],
		]);
	}

	protected static function action_logout_user( array $config ): array {
		$force = ! empty( $config['force'] );
		if ( $force || is_user_logged_in() ) {
			wp_logout();
		}

		return static::success( [ 'logged_out' => true ] );
	}

	protected static function action_activate_user( array $config ): array {
		$error = '';
		$user = static::require_user_from_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		$user_id = (int) $user->ID;

		delete_user_meta( $user_id, 'zaplane_user_deactivated' );
		if ( is_multisite() && function_exists( 'update_user_status' ) ) {
			// phpcs:ignore WordPress.WP.DeprecatedFunctions.update_user_statusFound -- Needed for multisite spam flag updates.
			update_user_status( $user_id, 'spam', 0 );
		}

		return static::success( [
			'user_id' => $user_id,
			'active' => true
		] );
	}

	protected static function action_deactivate_user( array $config ): array {
		$error = '';
		$user = static::require_user_from_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		$user_id = (int) $user->ID;

		update_user_meta( $user_id, 'zaplane_user_deactivated', 1 );
		if ( is_multisite() && function_exists( 'update_user_status' ) ) {
			// phpcs:ignore WordPress.WP.DeprecatedFunctions.update_user_statusFound -- Needed for multisite spam flag updates.
			update_user_status( $user_id, 'spam', 1 );
		}

		return static::success( [
			'user_id' => $user_id,
			'active' => false
		] );
	}
}
