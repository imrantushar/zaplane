<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Core\IntegrationLoader;
use Zaplane\Framework\Core\IntegrationManifest;
use Zaplane\CustomApps\ManifestStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BuildIntegrationCommand extends Command {

	protected string $signature = 'build:integration';
	protected string $description = 'Generate integrations.json manifest from registered integrations';

	/**
	 * Drop per-site state from a manifest entry.
	 *
	 * Some integrations mark a capability `disabled` by asking whether a
	 * companion addon is active right now — StoreEngine gates its subscription
	 * actions that way. That answer belongs to whichever site is being asked, so
	 * freezing it into a file that ships to every site is wrong in both
	 * directions: build with the addon on and customers without it never see the
	 * warning, build with it off and customers who have it see the capability
	 * greyed out forever.
	 *
	 * `requires_addon` stays, because "this needs addon X" is true everywhere and
	 * is the durable half of the same fact. Whether X is active is then a runtime
	 * question for the site to answer.
	 *
	 * @param array<string,mixed> $entry
	 * @return array<string,mixed>
	 */
	private static function strip_runtime_state( array $entry ): array {
		foreach ( [ 'triggers', 'actions' ] as $bucket ) {
			if ( empty( $entry[ $bucket ] ) || ! is_array( $entry[ $bucket ] ) ) {
				continue;
			}

			foreach ( $entry[ $bucket ] as $key => $capability ) {
				if ( ! is_array( $capability ) ) {
					continue;
				}
				unset( $capability['disabled'], $capability['disabled_reason'] );
				$entry[ $bucket ][ $key ] = $capability;
			}
		}

		return $entry;
	}

	public function handle( array $args, array $assoc_args ): void {
		$this->info( '🔨 Building integrations.json manifest...' );

		$manifest = [
			'version'      => defined( 'ZAPLANE_VERSION' ) ? ZAPLANE_VERSION : '1.0.0',
			'generated_at' => current_time( 'mysql' ),
			'apps'         => [],
			'tools'        => [],
		];

		$appCount = 0;
		$toolCount = 0;

		// User-defined Custom Apps register at runtime and are merged into the
		// frontend manifest live (see Admin\Assets::get_frontend_integrations()).
		// They must never be frozen into the static catalogue: once baked in they
		// linger in the picker even after the app is deleted from the store.
		$custom_slugs = array_map( 'strval', array_keys( ManifestStore::all() ) );

		foreach ( IntegrationLoader::all() as $slug => $instance ) {
			if ( in_array( (string) $slug, $custom_slugs, true ) ) {
				$this->line( "  ↷ {$slug} (custom app) - merged live, skipping static build" );
				continue;
			}

			$integration = IntegrationManifest::build_entry( get_class( $instance ), (string) $slug );
			if ( null === $integration ) {
				$this->warning( "⚠️  Could not build manifest entry for {$slug} - skipping" );
				continue;
			}

			$integration = self::strip_runtime_state( $integration );

			if ( 'tool' === $integration['category'] ) {
				$manifest['tools'][ $slug ] = $integration;
				$toolCount++;
				$this->line( "  ✓ {$slug} (tool) - " . count( $integration['triggers'] ) . ' triggers, ' . count( $integration['actions'] ) . ' actions' );
			} else {
				$manifest['apps'][ $slug ] = $integration;
				$appCount++;
				$triggerCount = count( $integration['triggers'] );
				$actionCount = count( $integration['actions'] );
				$this->line( "  ✓ {$slug} (app) - {$triggerCount} triggers, {$actionCount} actions" );
			}
		}//end foreach

		$assetsDir = ZAPLANE_ROOT_DIR_PATH . 'assets/json';
		$file = $assetsDir . '/integrations.json';

		if ( ! is_dir( $assetsDir ) ) {
			if ( ! mkdir( $assetsDir, 0755, true ) ) {
				$this->error( 'Failed to create assets/json/ directory' );
				return;
			}
		}

		if ( ! is_writable( $assetsDir ) ) {
			$this->error( 'assets/json/ folder is not writable' );
			return;
		}

		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		// Never let the build machine's hostname reach the shipped catalogue.
		if ( is_string( $json ) ) {
			$json = str_replace( rest_url(), IntegrationManifest::REST_URL_TOKEN, $json );
		}

		if ( false === $json ) {
			$this->error( 'Failed to encode JSON' );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( file_put_contents( $file, $json ) === false ) {
			$this->error( 'Failed to write integrations.json file' );
			return;
		}

		$this->line( '' );
		$this->success( '✅ Integrations manifest built successfully!' );
		$this->line( '' );
		$this->line( "  📦 Apps:	 {$appCount}" );
		$this->line( "  🔧 Tools: {$toolCount}" );
		$this->line( "  📄 File:	 {$file}" );
		$this->line( '' );
	}
}
