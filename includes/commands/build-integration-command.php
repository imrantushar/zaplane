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
