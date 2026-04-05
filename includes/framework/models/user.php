<?php

namespace Zaplane\Framework\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class User extends WpModel {

	protected static string $table = 'users';
	protected static string $primaryKey = 'ID';
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'user_login',
		'user_pass',
		'user_nicename',
		'user_email',
		'user_url',
		'user_registered',
		'user_activation_key',
		'user_status',
		'display_name',
	];

	protected static array $hidden = [
		'user_pass',
		'user_activation_key',
	];

	protected static array $casts = [
		'ID' => 'integer',
		'user_status' => 'integer',
	];

	public function posts(): array {
		return Post::where( 'post_author', $this->ID )->get();
	}

	public function getMeta( string $key, bool $single = true ) {
		return get_user_meta( $this->ID, $key, $single );
	}

	public function setMeta( string $key, $value ): bool {
		return (bool) update_user_meta( $this->ID, $key, $value );
	}

	public function deleteMeta( string $key, $value = '' ): bool {
		return delete_user_meta( $this->ID, $key, $value );
	}

	public function hasRole( string $role ): bool {
		$user = get_user_by( 'ID', $this->ID );
		return $user && in_array( $role, $user->roles, true );
	}

	public function hasCapability( string $capability ): bool {
		return user_can( $this->ID, $capability );
	}

	public static function current(): ?self {
		$userId = get_current_user_id();
		if ( ! $userId ) {
			return null;
		}
		return static::find( $userId );
	}

	public static function byEmail( string $email ): ?self {
		return static::where( 'user_email', $email )->first();
	}

	public static function byLogin( string $login ): ?self {
		return static::where( 'user_login', $login )->first();
	}

	public static function admins(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom admin role query.
		$adminIds = $wpdb->get_col(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '{$wpdb->prefix}capabilities' AND meta_value LIKE '%administrator%'"
		);
		if ( empty( $adminIds ) ) {
			return [];
		}
		return static::whereIn( 'ID', $adminIds )->get();
	}
}
