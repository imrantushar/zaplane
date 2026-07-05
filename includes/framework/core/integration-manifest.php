<?php

namespace Zaplane\Framework\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the apps/tools manifest the dashboard frontend consumes.
 *
 * The same shape is written to assets/json/integrations.json by the
 * `build:integration` CLI command AND merged live at page load so that
 * runtime-registered integrations (Custom Apps) show up without a rebuild.
 *
 * @see \Zaplane\Commands\BuildIntegrationCommand
 * @see \Zaplane\Admin\Assets::enqueue_app_assets()
 */
class IntegrationManifest {

	/**
	 * Build the full manifest (apps + tools) from a set of integration instances.
	 *
	 * @param array<string,object> $instances Slug => instance, e.g. IntegrationLoader::all().
	 * @return array<string,mixed>
	 */
	public static function build( array $instances ): array {
		$manifest = [
			'version'      => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '1.0.0',
			'generated_at' => current_time( 'mysql' ),
			'apps'         => [],
			'tools'        => [],
		];

		foreach ( $instances as $slug => $instance ) {
			$entry = self::build_entry( get_class( $instance ), (string) $slug );
			if ( null === $entry ) {
				continue;
			}

			$bucket = 'tool' === $entry['category'] ? 'tools' : 'apps';
			$manifest[ $bucket ][ $slug ] = $entry;
		}

		return $manifest;
	}

	/**
	 * Build a single integration's manifest entry (identity + triggers + actions).
	 * Returns null when the class can't produce a valid entry.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function build_entry( string $class, string $slug ): ?array {
		if ( ! class_exists( $class ) ) {
			return null;
		}

		$category = method_exists( $class, 'get_category' ) ? $class::get_category() : 'app';

		$entry = [
			'slug'                => $slug,
			'name'                => $class::get_name(),
			'icon'                => $class::get_icon(),
			'category'            => $category,
			'requires_connection' => $class::requires_connection(),
			'auth_type'           => $class::get_auth_type(),
			'supports_webhook'    => $class::supports_webhook(),
			'triggers'            => [],
			'actions'             => [],
		];

		// Tools expose actions only — never triggers.
		if ( 'tool' !== $category ) {
			foreach ( $class::get_triggers() as $key => $trigger ) {
				// A missing hook is valid: manual/scheduled triggers don't fire from
				// a WP event (resolve_hook returns empty, so they're never auto-
				// registered) — they run on demand. Hook-based triggers still carry
				// their hook here.
				$entry['triggers'][ $key ] = [
					'key'     => $key,
					'label'   => $trigger['label'],
					'hook'    => $trigger['hook'] ?? '',
					'schema'  => method_exists( $class, 'get_trigger_config_schema' )
						? $class::get_trigger_config_schema( $key )
						: [],
					'outputs' => $class::get_output_ports(),
				];
			}
		}

		foreach ( $class::get_actions() as $key => $action ) {
			$entry['actions'][ $key ] = [
				'key'     => $key,
				'label'   => $action['label'],
				'schema'  => method_exists( $class, 'get_action_config_schema' )
					? $class::get_action_config_schema( $key )
					: [],
				'outputs' => $class::get_output_ports(),
			];
		}

		return $entry;
	}
}
