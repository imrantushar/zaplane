<?php
namespace Zaplane\Integrations\Zencommunity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait ActionsResponseTrait {

	protected static function action_error( string $message, array $input = [] ): array {
		return [
			'port' => 'main',
			'data' => array_merge( $input, [
				'error' => $message,
			] ),
		];
	}

	protected static function action_success( array $data = [] ): array {
		return [
			'port' => 'main',
			'data' => $data,
		];
	}

	/**
	 * Validate required values AFTER config → input → default resolution, so a
	 * value mapped from a previous node counts the same as one typed into the
	 * node's own config.
	 *
	 * @param array $values   Merged (input + config) view of the node's fields.
	 * @param array $required Field keys that must be present and non-empty.
	 * @param array $input    Parent node output, preserved on the error payload.
	 * @return array|null The error response, or null when everything is present.
	 */
	protected static function require_fields( array $values, array $required, array $input = [] ): ?array {
		foreach ( $required as $key ) {
			$value = $values[ $key ] ?? null;

			if ( null === $value || '' === $value || ( is_array( $value ) && empty( $value ) ) ) {
				return self::action_error( sprintf( 'Missing required field: %s', $key ), $input );
			}
		}

		return null;
	}

	/**
	 * Gate for the actions only ZenCommunity Pro can execute. Returns null to
	 * proceed when the Pro addon is detectable, otherwise an error response —
	 * so the node degrades to a readable message instead of a fatal.
	 *
	 * @return array|null
	 */
	protected static function require_pro( array $input = [] ): ?array {
		if ( self::is_pro_available() ) {
			return null;
		}

		return self::action_error( 'This action requires the ZenCommunity Pro addon.', $input );
	}

	protected static function is_pro_available(): bool {
		$available = defined( 'ZENCOMMUNITY_PRO_VERSION' )
			|| class_exists( 'ZenCommunityPro\ZenCommunityPro' )
			|| function_exists( 'zencommunity_pro' );

		if ( function_exists( 'apply_filters' ) ) {
			$available = (bool) apply_filters( 'zaplane_zencommunity_pro_available', $available );
		}

		return (bool) $available;
	}

	/**
	 * Hand a Pro-gated action to a handler registered by the Pro addon (or a
	 * bridge). Pro is not bundled with Zaplane, so without a registered
	 * handler the node reports that plainly instead of failing silently.
	 */
	protected static function pro_dispatch( string $event, array $config, array $input ): array {
		$result = function_exists( 'apply_filters' )
			? apply_filters( 'zaplane_zencommunity_pro_action', null, $event, $config, $input )
			: null;

		if ( is_array( $result ) && isset( $result['port'] ) ) {
			return $result;
		}

		return self::action_error( 'ZenCommunity Pro is active, but no handler is registered for this action yet.', $input );
	}
}
