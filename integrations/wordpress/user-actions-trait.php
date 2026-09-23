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

	/**
	 * The only fields a Create/Update User step may set.
	 *
	 * wp_insert_user() accepts far more than the step's form offers, so the
	 * step's own configuration is filtered against this list instead of being
	 * handed over whole. Anything else in the configuration — including keys a
	 * merge tag could have introduced at run time — is dropped rather than
	 * silently applied.
	 *
	 * A method rather than a constant: traits could not hold constants before
	 * PHP 8.2, and this plugin supports 7.4.
	 *
	 * @return array<int,string>
	 */
	private static function user_field_keys(): array {
		return [
			'user_login',
			'user_email',
			'user_pass',
			'user_url',
			'user_nicename',
			'display_name',
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'locale',
		];
	}

	/**
	 * The step's configuration, reduced to the fields above and sanitized.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @return array<string,mixed>
	 */
	private static function user_fields_from_config( array $config ): array {
		$fields = [];

		foreach ( self::user_field_keys() as $key ) {
			if ( ! isset( $config[ $key ] ) || '' === $config[ $key ] ) {
				continue;
			}

			$value = $config[ $key ];

			switch ( $key ) {
				case 'user_email':
					$value = sanitize_email( (string) $value );
					break;
				case 'user_login':
					$value = sanitize_user( (string) $value, true );
					break;
				case 'user_url':
					$value = esc_url_raw( (string) $value );
					break;
				case 'user_pass':
					$value = (string) $value;
					break;
				case 'description':
					$value = sanitize_textarea_field( (string) $value );
					break;
				default:
					$value = sanitize_text_field( (string) $value );
					break;
			}

			if ( '' === $value ) {
				continue;
			}

			$fields[ $key ] = $value;
		}

		return $fields;
	}

	/**
	 * Whether an email the step set is still there after sanitizing.
	 *
	 * sanitize_email() turns something that is not an address into an empty
	 * string, which the field filter then drops — so without this the step would
	 * report success having quietly ignored the address it was told to use.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @param array<string,mixed> $fields Fields that survived sanitizing.
	 */
	private static function email_survived_sanitizing( array $config, array $fields ): bool {
		$configured = trim( (string) ( $config['user_email'] ?? '' ) );

		if ( '' === $configured ) {
			return true;
		}

		return ! empty( $fields['user_email'] ) && is_email( $fields['user_email'] );
	}

	/**
	 * A role the site actually has, or nothing.
	 *
	 * An unknown role name reaching wp_insert_user() leaves the account with no
	 * role at all, which reads as a working step that quietly did the wrong
	 * thing, so it is refused here instead.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @return string|null Role name, or null when the step sets none.
	 */
	private static function role_from_config( array $config ): ?string {
		$role = sanitize_key( (string) ( $config['role'] ?? '' ) );

		if ( '' === $role ) {
			return null;
		}

		return get_role( $role ) ? $role : null;
	}

	/**
	 * Create a WordPress user.
	 *
	 * An automation exists to do what the site owner would otherwise do by hand,
	 * and "add the buyer as a user when the order completes" is one of the things
	 * people automate most. The step only ever runs as part of a workflow, and a
	 * workflow can only be created or edited by somebody with `manage_options` —
	 * the REST routes that save one check that on every call, and there is no
	 * public route that can add or alter a step. So the account this creates is
	 * one an administrator asked for in advance, with the role they chose.
	 *
	 * What it does not do is accept whatever the configuration happens to
	 * contain: the fields are filtered to the list above, the role must be one
	 * the site defines, and a missing password is generated rather than left
	 * blank.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @return array<string,mixed>
	 */
	protected static function action_create_user( array $config ): array {
		$fields = self::user_fields_from_config( $config );

		if ( ! self::email_survived_sanitizing( $config, $fields ) ) {
			return static::error( 'A valid email address is required' );
		}

		if ( empty( $fields['user_login'] ) || empty( $fields['user_email'] ) ) {
			return static::error( 'A username and an email address are required' );
		}

		if ( empty( $fields['user_pass'] ) ) {
			$fields['user_pass'] = wp_generate_password( 24, true, true );
		}

		$role = self::role_from_config( $config );
		if ( null !== $role ) {
			$fields['role'] = $role;
		}

		$user_id = wp_insert_user( $fields );

		if ( is_wp_error( $user_id ) ) {
			return static::error( $user_id->get_error_message() );
		}

		return static::success( [ 'user_id' => $user_id ] );
	}

	/**
	 * Update an existing user.
	 *
	 * Same reasoning as {@see self::action_create_user()}: authored by an
	 * administrator, limited to the fields the step's form offers, and to a role
	 * the site defines.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @return array<string,mixed>
	 */
	protected static function action_update_user( array $config ): array {
		$error = '';
		$user  = static::require_user_from_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}

		$fields = self::user_fields_from_config( $config );

		// A login cannot be changed after the account exists, and letting the
		// step try only produces a confusing error.
		unset( $fields['user_login'] );

		if ( ! self::email_survived_sanitizing( $config, $fields ) ) {
			return static::error( 'A valid email address is required' );
		}

		$fields['ID'] = (int) $user->ID;

		$role = self::role_from_config( $config );
		if ( null !== $role ) {
			$fields['role'] = $role;
		}

		$updated = wp_update_user( $fields );

		if ( is_wp_error( $updated ) ) {
			return static::error( $updated->get_error_message() );
		}

		return static::success( [ 'updated' => $updated ] );
	}

	/**
	 * Delete a user, optionally reassigning their content.
	 *
	 * Deleting people is the one step here that cannot be undone, so it refuses
	 * the two cases that would lock a site out of itself: the last remaining
	 * administrator, and the account the request is running as.
	 *
	 * @param array<string,mixed> $config Step configuration.
	 * @return array<string,mixed>
	 */
	protected static function action_delete_user( array $config ): array {
		$error = '';
		$user  = static::require_user_from_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}

		$user_id = (int) $user->ID;

		if ( get_current_user_id() === $user_id ) {
			return static::error( 'A workflow cannot delete the account it is running as' );
		}

		if ( user_can( $user_id, 'manage_options' ) && count( get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] ) ) <= 1 ) {
			return static::error( 'This is the only administrator on the site and cannot be deleted' );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$reassign = isset( $config['reassign'] ) && '' !== $config['reassign'] ? (int) $config['reassign'] : null;

		if ( null !== $reassign && ! get_userdata( $reassign ) ) {
			return static::error( 'The user to reassign content to does not exist' );
		}

		$deleted = wp_delete_user( $user_id, $reassign );

		if ( ! $deleted ) {
			return static::error( "Failed to delete user ID {$user_id}" );
		}

		return static::success( [ 'deleted_user_id' => $user_id ] );
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

	/*
	 * There is deliberately no action that signs a user in. It used to call
	 * wp_signon() with a username and password stored in the workflow, which set
	 * auth cookies on whatever request happened to be running the workflow —
	 * meaning a visitor whose page view started the run could end up signed in as
	 * that account. Signing in is the visitor's own act, through WordPress's own
	 * login, and nothing here should stand in for it.
	 */

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
