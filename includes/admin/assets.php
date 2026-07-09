<?php
namespace Zaplane\Admin;

use Zaplane\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_app_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_icons' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_icons' ] );
	}

	public function enqueue_icons(): void {
		wp_enqueue_style(
			'zaplane-icons',
			ZAPLANE_ASSETS_URI . 'library/icons/zaplane-icons.css',
			[],
			filemtime( ZAPLANE_ASSETS_DIR_PATH . 'library/icons/zaplane-icons.css' )
		);
	}

	public function enqueue_app_assets( $hook ) {
		if ( strpos( $hook, '_page_' . ZAPLANE_PLUGIN_SLUG ) !== false ) {
			remove_all_actions( 'admin_notices' );
			$dependencies = include_once ZAPLANE_ASSETS_DIR_PATH . 'build/app.asset.php';
			// Load the WP media library so file-picker fields (e.g. CSV Parse) can
			// open the uploader via window.wp.media.
			wp_enqueue_media();
			wp_enqueue_style( 'zaplane-app-style', ZAPLANE_ASSETS_URI . 'build/app.css', [ 'wp-components' ], filemtime( ZAPLANE_ASSETS_DIR_PATH . 'build/app.css' ), 'all' );
			wp_enqueue_script(
				'zaplane-app-scripts',
				ZAPLANE_ASSETS_URI . 'build/app.js',
				$dependencies['dependencies'],
				$dependencies['version'],
				true
			);
			wp_localize_script('zaplane-app-scripts', 'ZaplaneGlobal', [
				'nonce'                 => wp_create_nonce( 'wp_rest' ),
				'zaplane_nonce'        => wp_create_nonce( 'zaplane_nonce' ),
				'namespace'             => ZAPLANE_PLUGIN_SLUG . '/v1/',
				'rest_url'              => rest_url(),
				'ajaxurl'               => esc_url( admin_url( 'admin-ajax.php' ) ),
				'site_url'              => site_url(),
				'admin_url'             => admin_url(),
				'route_path'            => wp_parse_url( admin_url(), PHP_URL_PATH ),
				'plugin_root_url'       => ZAPLANE_PLUGIN_ROOT_URI,
				'menu'                  => wp_json_encode( Helper::get_admin_menu_list() ),
				'integrations'          => $this->get_frontend_integrations(),
			]);
			wp_set_script_translations( 'zaplane-app-scripts', 'zaplane', ZAPLANE_ROOT_DIR_PATH . 'languages/' );
		}//end if
	}

	/**
	 * The integrations manifest sent to the dashboard: the pre-built built-in
	 * catalogue from integrations.json, plus any user-defined Custom Apps merged
	 * in live. Custom apps register at runtime (via the `zaplane_integrations`
	 * filter) and are never written to the static file, so without this merge a
	 * newly created custom app can't be picked when building a workflow.
	 *
	 * @return array<string,mixed>
	 */
	private function get_frontend_integrations(): array {
		$integrations = [ 'apps' => [], 'tools' => [] ];

		$file = ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json';
		if ( is_readable( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$decoded = json_decode( file_get_contents( $file ), true );
			if ( is_array( $decoded ) ) {
				$integrations = $decoded;
			}
		}

		$integrations['apps']  = $integrations['apps'] ?? [];
		$integrations['tools'] = $integrations['tools'] ?? [];

		foreach ( \Zaplane\CustomApps\ManifestStore::all() as $slug => $manifest ) {
			$slug        = (string) $slug;
			$integration = \Zaplane\Framework\Core\IntegrationLoader::get( $slug );
			if ( ! $integration ) {
				continue;
			}

			$entry = \Zaplane\Framework\Core\IntegrationManifest::build_entry( get_class( $integration ), $slug );
			if ( null === $entry ) {
				continue;
			}

			$bucket = 'tool' === $entry['category'] ? 'tools' : 'apps';
			$integrations[ $bucket ][ $slug ] = $entry;
		}

		return $integrations;
	}
}
