<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps an HTTP response body into the named outputs a Custom App exposes to
 * downstream nodes, and extracts item lists for polling triggers.
 *
 * Paths are dot-notation into the decoded JSON body, e.g. "data.id" or
 * "items.0.name". An empty path means "the whole body".
 */
class ResponseMapper {

	/**
	 * Extract a single value at a dot-path. Empty path returns the whole body.
	 *
	 * @param mixed  $body
	 * @param string $path
	 * @return mixed
	 */
	public static function extract( $body, string $path ) {
		if ( '' === $path ) {
			return $body;
		}
		if ( ! is_array( $body ) && ! is_object( $body ) ) {
			return null;
		}
		return Template::resolve_path( (array) $body, $path );
	}

	/**
	 * Build the output map for an action from its `output` definition:
	 *   [ { "key": "deal_id", "path": "data.id" }, ... ]
	 *
	 * When no outputs are declared, the decoded body is returned under `response`
	 * so downstream nodes can still reach into it with dot notation.
	 *
	 * @param mixed $body
	 * @param array $outputs
	 * @return array<string,mixed>
	 */
	public static function map_outputs( $body, array $outputs ): array {
		if ( empty( $outputs ) ) {
			return [ 'response' => $body ];
		}

		$mapped = [];
		foreach ( $outputs as $output ) {
			if ( ! is_array( $output ) || empty( $output['key'] ) ) {
				continue;
			}
			$key            = (string) $output['key'];
			$path           = isset( $output['path'] ) ? (string) $output['path'] : '';
			$mapped[ $key ] = self::extract( $body, $path );
		}

		// Always keep the raw body available too, without clobbering a mapped key.
		if ( ! array_key_exists( 'response', $mapped ) ) {
			$mapped['response'] = $body;
		}

		return $mapped;
	}

	/**
	 * Extract the list of items a polling trigger should iterate over.
	 *
	 * @param mixed  $body
	 * @param string $items_path
	 * @return array<int,mixed> Always a list; non-array results become [].
	 */
	public static function extract_items( $body, string $items_path ): array {
		$items = self::extract( $body, $items_path );
		if ( ! is_array( $items ) ) {
			return [];
		}
		// Force a list even if the API returned an associative map of items.
		return array_values( $items );
	}
}
