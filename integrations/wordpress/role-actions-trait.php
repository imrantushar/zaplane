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
		$user = get_userdata( (int) ( $config['user_id'] ?? 0 ) );
		return $user ? $user : null;
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
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return static::error( 'Role not found' );
		}
		$user->add_role( $role_key );
		return static::success();
	}

	protected static function action_remove_user_role( array $config ): array {
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}
		$role_key = static::resolve_role_key_from_config( $config, true );
		if ( '' === $role_key ) {
			return static::error( 'Role not found' );
		}
		$user->remove_role( $role_key );
		return static::success();
	}

	protected static function action_update_user_role( array $config ): array {
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			return static::error( 'User not found' );
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
		$role = static::resolve_role( $config );
		if ( ! $role ) {
			return static::error( 'Role not found' );
		}
		return static::success( array_keys( $role->capabilities ?? [] ) );
	}

	protected static function action_add_role_caps( array $config ): array {
		$role = static::resolve_role( $config );
		if ( ! $role ) {
			return static::error( 'Role not found' );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$role->add_cap( $cap );
		}
		return static::success();
	}

	protected static function action_remove_role_caps( array $config ): array {
		$role = static::resolve_role( $config );
		if ( ! $role ) {
			return static::error( 'Role not found' );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$role->remove_cap( $cap );
		}
		return static::success();
	}

	protected static function action_get_user_caps( array $config ): array {
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}
		return static::success( array_keys( $user->allcaps ?? [] ) );
	}

	protected static function action_add_user_caps( array $config ): array {
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$user->add_cap( $cap );
		}
		return static::success();
	}

	protected static function action_remove_user_caps( array $config ): array {
		$user = static::resolve_user( $config );
		if ( ! $user ) {
			return static::error( 'User not found' );
		}
		foreach ( self::normalize_list( $config['caps'] ?? [] ) as $cap ) {
			$user->remove_cap( $cap );
		}
		return static::success();
	}
}
