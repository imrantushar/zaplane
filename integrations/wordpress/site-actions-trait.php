<?php
namespace Zaplane\Integrations\Wordpress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Network actions. They only do anything on a multisite install, and say so
 * plainly when the site is a single one.
 */
trait SiteActionsTrait {

	private static function require_multisite(): ?array {
		if ( ! is_multisite() ) {
			return static::error( 'This action needs a multisite network; this is a single site.' );
		}
		return null;
	}

	protected static function action_create_site( array $config ): array {
		$blocked = self::require_multisite();
		if ( $blocked ) {
			return $blocked;
		}

		$domain  = (string) ( $config['domain'] ?? '' );
		$path    = (string) ( $config['path'] ?? '' );
		$title   = (string) ( $config['title'] ?? '' );
		$user_id = (int) ( $config['user_id'] ?? 0 );

		if ( '' === $domain || '' === $title || ! $user_id ) {
			return static::error( 'Domain, title and user ID are required' );
		}

		$site_id = wpmu_create_blog( $domain, '' === $path ? '/' : $path, $title, $user_id );
		if ( is_wp_error( $site_id ) ) {
			return static::error( $site_id->get_error_message() );
		}

		return static::success( [
			'site_id' => (int) $site_id,
			'url'     => get_site_url( (int) $site_id ),
		] );
	}

	protected static function action_delete_site( array $config ): array {
		$blocked = self::require_multisite();
		if ( $blocked ) {
			return $blocked;
		}

		$site_id = (int) ( $config['site_id'] ?? 0 );
		if ( ! $site_id ) {
			return static::error( 'Site ID is required' );
		}
		if ( get_main_site_id() === $site_id ) {
			return static::error( 'The main site of the network cannot be deleted.' );
		}
		if ( ! get_site( $site_id ) ) {
			return static::error( "Site ID {$site_id} not found" );
		}

		$result = wp_delete_site( $site_id );
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}

		return static::success( [
			'deleted' => true,
			'site_id' => $site_id,
		] );
	}

	protected static function action_add_user_to_site( array $config ): array {
		$blocked = self::require_multisite();
		if ( $blocked ) {
			return $blocked;
		}

		$site_id = (int) ( $config['site_id'] ?? 0 );
		$user_id = (int) ( $config['user_id'] ?? 0 );
		$role    = (string) ( $config['role'] ?? 'subscriber' );

		if ( ! $site_id || ! $user_id ) {
			return static::error( 'Site ID and user ID are required' );
		}

		$result = add_user_to_blog( $site_id, $user_id, '' === $role ? 'subscriber' : $role );
		if ( is_wp_error( $result ) ) {
			return static::error( $result->get_error_message() );
		}

		return static::success( [
			'site_id' => $site_id,
			'user_id' => $user_id,
			'role'    => $role,
		] );
	}

	protected static function action_remove_user_from_site( array $config ): array {
		$blocked = self::require_multisite();
		if ( $blocked ) {
			return $blocked;
		}

		$site_id = (int) ( $config['site_id'] ?? 0 );
		$user_id = (int) ( $config['user_id'] ?? 0 );

		if ( ! $site_id || ! $user_id ) {
			return static::error( 'Site ID and user ID are required' );
		}

		$removed = remove_user_from_blog( $user_id, $site_id );
		if ( is_wp_error( $removed ) ) {
			return static::error( $removed->get_error_message() );
		}

		return static::success( [
			'site_id' => $site_id,
			'user_id' => $user_id,
			'removed' => true,
		] );
	}
}
