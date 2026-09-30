<?php

namespace Zaplane\Framework\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Option extends WpModel {

	protected static string $table = 'options';
	protected static string $primaryKey = 'option_id';
	protected static bool $timestamps = false;

	protected static array $fillable = [
		'option_name',
		'option_value',
		'autoload',
	];

	protected static array $casts = [
		'option_id' => 'integer',
	];

	public function getValue() {
		$value = $this->option_value;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress options use serialization.
		$unserialized = maybe_unserialize( $value );
		return $unserialized !== $value ? $unserialized : $value;
	}

	public static function get( string $name, $default = null ) {
		$option = static::where( 'option_name', $name )->fresh()->first();
		if ( ! $option ) {
			return $default;
		}
		return $option->getValue();
	}

	public static function set( string $name, $value, string $autoload = 'yes' ): bool {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Required for WP option storage compatibility.
		$serialized = is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;

		// Not from the query cache: a second write in the same request, such as two
		// triggers saving the same sample, would otherwise insert the row again.
		$existing = static::where( 'option_name', $name )->fresh()->first();

		if ( $existing ) {
			$existing->option_value = $serialized;
			$saved                  = $existing->save();
		} else {
			$saved = (bool) static::create([
				'option_name' => $name,
				'option_value' => $serialized,
				'autoload' => $autoload,
			]);
		}

		if ( $saved ) {
			// The write went straight to the table, so WordPress's cached copy
			// of the option — runtime, and persistent on sites running an
			// object cache — still holds the old value until it is dropped.
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}

		return $saved;
	}

	public static function remove( string $name ): bool {
		$option = static::where( 'option_name', $name )->fresh()->first();
		if ( ! $option ) {
			return false;
		}

		$deleted = $option->delete();

		if ( $deleted ) {
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}

		return $deleted;
	}
}
