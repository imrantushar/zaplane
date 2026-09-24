<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * {{ placeholder }} interpolation for Custom App request templates.
 *
 * By the time a node runs, the workflow engine has already resolved its
 * `@`-variables into concrete config values. The manifest's request template
 * then references those values (and the connection credentials) with a simple
 * moustache syntax:
 *
 *     "Authorization": "Bearer {{ creds.access_token }}"
 *     "body": { "title": "{{ title }}", "value": "{{ amount }}" }
 *
 * Resolution rules mirror the HTTP integration: when a placeholder is the ENTIRE
 * string, the raw resolved value is returned (so an array/number survives as-is
 * and can be JSON-encoded downstream). When embedded in surrounding text, the
 * value is stringified. Runtime input from an upstream node is available under
 * `input` (for example `{{ input.order_id }}`).
 */
class Template {

	/**
	 * Recursively interpolate a value (string, array, or scalar) against a context.
	 *
	 * @param mixed               $value   The template value.
	 * @param array<string,mixed> $context Resolution context (see build_context()).
	 * @return mixed
	 */
	public static function interpolate( $value, array $context ) {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				// Keys can be templated too (rare, but cheap to support).
				$key         = is_string( $k ) ? self::interpolate_string( $k, $context ) : $k;
				$out[ $key ] = self::interpolate( $v, $context );
			}
			return $out;
		}

		if ( is_string( $value ) ) {
			return self::interpolate_string( $value, $context );
		}

		return $value;
	}

	/**
	 * Interpolate a single string. Returns the raw resolved value when the whole
	 * string is one placeholder; otherwise a string with each placeholder replaced.
	 *
	 * @param array<string,mixed> $context
	 * @return mixed
	 */
	protected static function interpolate_string( string $value, array $context ) {
		if ( '' === $value || false === strpos( $value, '{{' ) ) {
			return $value;
		}

		// Whole-string placeholder: return the raw value (may be non-scalar).
		if ( preg_match( '/^\s*\{\{\s*([^}]+?)\s*\}\}\s*$/', $value, $m ) ) {
			$resolved = self::resolve_path( $context, trim( $m[1] ) );
			return null === $resolved ? '' : $resolved;
		}

		// Embedded placeholders: replace inline, stringifying each value.
		return preg_replace_callback(
			'/\{\{\s*([^}]+?)\s*\}\}/',
			static function ( $match ) use ( $context ) {
				$resolved = self::resolve_path( $context, trim( $match[1] ) );
				return self::stringify( $resolved );
			},
			$value
		);
	}

	/**
	 * Resolve a dot-path ("creds.access_token", "config.title", "title") against
	 * the context. Returns null when any segment is missing.
	 *
	 * @param array<string,mixed> $context
	 * @return mixed
	 */
	public static function resolve_path( array $context, string $path ) {
		if ( '' === $path ) {
			return null;
		}

		$segments = explode( '.', $path );
		$current   = $context;

		foreach ( $segments as $segment ) {
			if ( is_array( $current ) && array_key_exists( $segment, $current ) ) {
				$current = $current[ $segment ];
				continue;
			}
			if ( is_object( $current ) && isset( $current->$segment ) ) {
				$current = $current->$segment;
				continue;
			}
			return null;
		}

		return $current;
	}

	/**
	 * Build the resolution context shared by every request template of a node.
	 * Field values are exposed both at the top level ({{ title }}) and under a
	 * `config` namespace ({{ config.title }}); credentials live under `creds`.
	 *
	 * @param array<string,mixed> $config      Resolved node field values.
	 * @param array<string,mixed> $credentials Decrypted connection credentials.
	 * @param array<string,mixed> $input       Upstream node output / trigger data.
	 * @return array<string,mixed>
	 */
	public static function build_context( array $config, array $credentials = [], array $input = [] ): array {
		return array_merge(
			$config,
			[
				'config' => $config,
				'creds'  => $credentials,
				'input'  => $input,
			]
		);
	}

	/**
	 * Coerce a resolved value into a string for inline interpolation.
	 *
	 * @param mixed $value
	 */
	protected static function stringify( $value ): string {
		if ( null === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		// Arrays/objects embedded in a larger string are JSON-encoded.
		return (string) wp_json_encode( $value );
	}
}
