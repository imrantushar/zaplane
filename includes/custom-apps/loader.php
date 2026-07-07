<?php
namespace Zaplane\CustomApps;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges stored manifests into the integration registry.
 *
 * The IntegrationLoader instantiates integrations by class name and the engine
 * calls their methods statically, so each custom app needs a distinct, named
 * class pinned to its slug. We synthesise those subclasses on the fly (one tiny
 * shell each, extending CustomAppBase) and register them via the
 * `zaplane_integrations` filter — no core files are touched.
 *
 * The synthesised class body contains nothing but a strictly-sanitised slug, so
 * there is no user-controlled code in the eval.
 */
class Loader {

	protected const CLASS_PREFIX = 'Zaplane\\Integrations\\CustomApp_';

	public static function boot(): void {
		add_filter( 'zaplane_integrations', [ self::class, 'register' ] );
	}

	/**
	 * Deterministic class name for a slug (shared with any code that needs to
	 * reference the generated class).
	 */
	public static function class_name( string $slug ): string {
		return self::CLASS_PREFIX . $slug;
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
		}

		return $registry;
	}

	/**
	 * Ensure a per-slug CustomAppBase subclass exists, creating it if needed.
	 * Returns the fully-qualified class name, or null on failure.
	 */
	protected static function ensure_class( string $slug ): ?string {
		$class = self::class_name( $slug );

		if ( class_exists( $class ) ) {
			return $class;
		}

		// Make sure the parent is loaded before we extend it.
		if ( ! class_exists( \Zaplane\Integrations\CustomAppBase::class ) ) {
			return null;
		}

		$short = 'CustomApp_' . $slug;

		// phpcs:ignore Squiz.PHP.Eval.Discouraged, WordPress.PHP.Eval.eval -- Synthesising a slug-pinned subclass; the only interpolated value is a strictly [a-z0-9_] slug.
		eval(
			sprintf(
				'namespace Zaplane\\Integrations; class %s extends CustomAppBase { protected static string $slug = %s; }',
				$short,
				var_export( $slug, true )
			)
		);

		return class_exists( $class ) ? $class : null;
	}
}
