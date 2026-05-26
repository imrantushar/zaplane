<?php
namespace Zaplane\Integrations\Wordpress;

use Zaplane\Traits\ActionResponseTrait;

trait RoleActionsTrait {

	private static function resolve_role_key_from_config( array $config, bool $require_existing = true ): string {
		return self::resolve_role_key( $config['role'] ?? '', $require_existing );
	}

	private static function resolve_role( array $config ) {
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return null;
		}
		$role = get_role( $role_key );
		return $role ? $role : null;
	}

	private static function resolve_user( array $config ) {
		$user_id = (int) ( $config['user_id'] ?? 0 );
		if ( ! $user_id ) {
			return null;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}
		// Role-mutation actions call $user->add_role()/remove_role()/set_role().
		// Some test environments mock get_userdata as a bare stdClass that lacks
		// these methods — fall back to a real WP_User instance in that case.
		if ( ! method_exists( $user, 'add_role' ) && class_exists( '\WP_User' ) ) {
			$user = new \WP_User( $user_id );
		}
		return $user;
	}

	private static function require_role_from_config( array $config, string &$error = '' ) {
		$role = static::resolve_role( $config );
		if ( ! $role ) {
			$error = 'Role not found';
			return null;
		}

		return $role;
	}

	private static function require_user_from_role_config( array $config, string &$error = '' ) {
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			$error = 'User not found';
			return null;
		}

		return $user;
	}

	protected static function action_create_role( array $config ): array {
		$role_key = static::resolve_role_key_from_config( $config, false );
		if ( '' === $role_key ) {
			return static::error( 'Role slug is required' );
		}
		$role = add_role(
			$role_key,
			$config['display_name'] ?? '',
			self::normalize_caps( $config['capabilities'] ?? [] )
		);
		if ( ! $role ) {
			return static::error( 'Failed to create role. Role may already exist.' );
		}

		return static::success( [ 'role' => self::format_role_payload( $role_key, $role ) ] );
	}

	protected static function action_delete_role( array $config ): array {
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return static::error( 'Role not found' );
		}
		return static::success( [ 'deleted' => remove_role( $role_key ) ] );
	}

	protected static function action_add_user_role( array $config ): array {
		$error = '';
		$user = static::require_user_from_role_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return static::error( 'Role not found' );
		}
		$user->add_role( $role_key );
		return static::success();
	}

	protected static function action_remove_user_role( array $config ): array {
		$error = '';
		$user = static::require_user_from_role_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return static::error( 'Role not found' );
		}
		$user->remove_role( $role_key );
		return static::success();
	}

	protected static function action_update_user_role( array $config ): array {
		$error = '';
		$user = static::require_user_from_role_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return static::error( 'Role not found' );
		}
		$user->set_role( $role_key );
		return static::success();
	}

	protected static function action_get_roles( array $config ): array {
		$roles = wp_roles()->roles ?? [];
		$search = strtolower( trim( (string) ( $config['search'] ?? '' ) ) );
		if ( '' !== $search ) {
			$roles = array_filter( $roles, function ( $role, $key ) use ( $search ) {
				$name = strtolower( (string) ( $role['name'] ?? '' ) );
				$key = strtolower( (string) $key );
				return false !== strpos( $name, $search ) || false !== strpos( $key, $search );
			}, ARRAY_FILTER_USE_BOTH );
		}
		return static::success( $roles );
	}

	protected static function action_get_caps( array $config ): array {
		$roles = wp_roles()->roles ?? [];
		$caps = [];
		foreach ( $roles as $role ) {
			foreach ( $role['capabilities'] ?? [] as $cap => $grant ) {
				if ( $grant ) {
					$caps[ $cap ] = true;
				}
			}
		}
		$items = array_keys( $caps );
		$search = strtolower( trim( (string) ( $config['search'] ?? '' ) ) );
		if ( '' !== $search ) {
			$items = array_values( array_filter( $items, function ( $cap ) use ( $search ) {
				return false !== strpos( strtolower( (string) $cap ), $search );
			} ) );
		}
		return static::success( $items );
	}

	protected static function action_get_role_caps( array $config ): array {
		$error = '';
		$role = static::require_role_from_config( $config, $error );
		if ( ! $role ) {
			return static::error( $error );
		}
		return static::success( array_keys( $role->capabilities ?? [] ) );
	}

	protected static function action_add_role_caps( array $config ): array {
		$error = '';
		$role = static::require_role_from_config( $config, $error );
		if ( ! $role ) {
			return static::error( $error );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$role->add_cap( $cap );
		}
		return static::success();
	}

	protected static function action_remove_role_caps( array $config ): array {
		$error = '';
		$role = static::require_role_from_config( $config, $error );
		if ( ! $role ) {
			return static::error( $error );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$role->remove_cap( $cap );
		}
		return static::success();
	}

	protected static function action_get_user_caps( array $config ): array {
		$error = '';
		$user = static::require_user_from_role_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		return static::success( array_keys( $user->allcaps ?? [] ) );
	}

	protected static function action_add_user_caps( array $config ): array {
		$error = '';
		$user = static::require_user_from_role_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$user->add_cap( $cap );
		}
		return static::success();
	}

	protected static function action_remove_user_caps( array $config ): array {
		$error = '';
		$user = static::require_user_from_role_config( $config, $error );
		if ( ! $user ) {
			return static::error( $error );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$user->remove_cap( $cap );
		}
		return static::success();
	}
}
