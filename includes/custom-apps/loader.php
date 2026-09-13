<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges stored manifests into the integration registry.
 *
 * The IntegrationLoader instantiates integrations by class name and the engine
 * calls their methods statically, so each custom app needs a class of its own
 * pinned to its slug. Those classes are a fixed pool declared in slots.php: a
 * manifest is bound to the next free slot as the registry is built through the
 * `zaplane_integrations` filter. No code is generated at run time.
 */
class Loader {

	protected const SLOT_PREFIX = 'Zaplane\\CustomApps\\Slots\\Slot';

	/**
	 * The slot class each custom app was bound to in this request.
	 *
	 * @var array<string,string>
	 */
	protected static array $bound = [];

	public static function boot(): void {
		add_filter( 'zaplane_integrations', [ self::class, 'register' ] );
	}

	/**
	 * The class a custom app is registered under, or '' before it is bound.
	 */
	public static function class_name( string $slug ): string {
		return self::$bound[ $slug ] ?? '';
	}

	/**
	 * Merge every stored manifest into the integration registry.
	 *
	 * @param array<string,array> $registry
	 * @return array<string,array>
	 */
	public static function register( array $registry ): array {
		foreach ( ManifestStore::all() as $slug => $manifest ) {
			$slug = ManifestValidator::sanitize_slug( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}

			// Never let a custom app shadow a built-in integration.
			if ( isset( $registry[ $slug ] ) ) {
				continue;
			}

			$class = self::ensure_class( $slug );
			if ( null === $class ) {
				continue;
			}

			$registry[ $slug ] = [
				'class' => $class,
				// Class already exists in memory, so the loader never reads this
				// path — it just needs a non-empty value to skip the file lookup.
				'path'  => ZAPLANE_INTEGRATION_DIR_PATH . 'custom-app-base.php',
			];
		}//end foreach

		return $registry;
	}

	/**
	 * Bind the custom app to a slot class, once per request.
	 * Returns the fully-qualified class name, or null when none is free.
	 */
	protected static function ensure_class( string $slug ): ?string {
		if ( isset( self::$bound[ $slug ] ) ) {
			return self::$bound[ $slug ];
		}

		// Make sure the parent is loaded before a slot extends it.
		if ( ! class_exists( \Zaplane\Integrations\CustomAppBase::class ) ) {
			return null;
		}

		require_once __DIR__ . '/slots.php';

		$class = self::SLOT_PREFIX . ( count( self::$bound ) + 1 );
		if ( ! class_exists( $class, false ) ) {
			return null; // Every slot is taken.
		}

		$class::bind_slug( $slug );
		self::$bound[ $slug ] = $class;

		return $class;
	}
}
