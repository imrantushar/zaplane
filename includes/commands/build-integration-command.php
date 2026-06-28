<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Core\IntegrationLoader;

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

		foreach ( IntegrationLoader::all() as $slug => $instance ) {
			$class = get_class( $instance );

			$category = method_exists( $class, 'get_category' )
				? $class::get_category()
				: 'app';

			$integration = [
				'slug'               => $slug,
				'name'               => $class::get_name(),
				'icon'               => $class::get_icon(),
				'category'           => $category,
				'requires_connection' => $class::requires_connection(),
				'auth_type'          => $class::get_auth_type(),
				'supports_webhook'   => $class::supports_webhook(),
				'webhook_url'        => $class::supports_webhook() ? rest_url( $class::get_webhook_url() ) : '',
				'triggers'           => [],
				'actions'            => [],
			];

			foreach ( $class::get_triggers() as $key => $trigger ) {
				if ( 'tool' === $category ) {
					continue;
				}

				if ( ! isset( $trigger['hook'] ) ) {
					$this->warning( "⚠️  Trigger '{$key}' in {$slug} is missing 'hook' field - skipping" );
					continue;
				}

				// Merge the raw definition first so optional flags an integration
				// sets on an item (e.g. disabled, disabled_reason, requires_addon)
				// survive into the manifest, then enforce the canonical fields.
				$integration['triggers'][ $key ] = array_merge( $trigger, [
					'key'     => $key,
					'label'   => $trigger['label'],
					'hook'    => $trigger['hook'],
					'schema'  => method_exists( $class, 'get_trigger_config_schema' )
						? $class::get_trigger_config_schema( $key )
						: [],
					'outputs' => $class::get_output_ports(),
				] );
			}

			foreach ( $class::get_actions() as $key => $action ) {
				$integration['actions'][ $key ] = array_merge( $action, [
					'key'     => $key,
					'label'   => $action['label'],
					'schema'  => method_exists( $class, 'get_action_config_schema' )
						? $class::get_action_config_schema( $key )
						: [],
					'outputs' => $class::get_output_ports(),
				] );
			}

			if ( 'tool' === $category ) {
				$manifest['tools'][ $slug ] = $integration;
				$toolCount++;
				$this->line( "  ✓ {$slug} (tool) - " . count( $integration['actions'] ) . ' actions' );
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
