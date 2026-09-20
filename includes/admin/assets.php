<?php
namespace Zaplane\Admin;

use Zaplane\Utils\Helper;
use Zaplane\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_app_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_icons' ] );
		// Zaplane only renders in wp-admin (the builder); its icon font is not used
		// on the front-end, so it isn't enqueued there — that avoided loading the
		// stylesheet (and a filemtime stat) on every public page view.
	}

	public function enqueue_icons( $hook ): void {
		// Only Zaplane's own screens use the icon font; the admin menu icon is an SVG.
		if ( false === strpos( (string) $hook, '_page_' . ZAPLANE_PLUGIN_SLUG ) ) {
			return;
		}

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
			// Admin-configured theme palettes, emitted as scoped CSS variables so
			// they override the SCSS :root defaults with no color flash on load.
			wp_add_inline_style( 'zaplane-app-style', $this->get_theme_inline_css() );
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
				'settings'              => Settings::get(),
			]);
			// The integrations catalogue is ~1.2 MB. Inject it as a raw JSON string
			// rather than through wp_localize_script, which would PHP-decode the
			// file only to re-encode the whole array. Runs after the localized
			// object above is declared, before app.js.
			wp_add_inline_script(
				'zaplane-app-scripts',
				'window.ZaplaneGlobal=window.ZaplaneGlobal||{};window.ZaplaneGlobal.integrations=' . $this->get_frontend_integrations_json() . ';',
				'before'
			);
			wp_set_script_translations( 'zaplane-app-scripts', 'zaplane', ZAPLANE_ROOT_DIR_PATH . 'languages/' );
		}//end if
	}

	/**
	 * Build the scoped theme stylesheet: light palette on `.zaplane-scope`, dark
	 * palette on `.zaplane-scope[data-theme="dark"]`. app.js stamps the data-theme
	 * attribute from the saved/preferred mode before React paints.
	 */
	private function get_theme_inline_css(): string {
		$theme = Settings::theme();
		$light = isset( $theme['light'] ) && is_array( $theme['light'] ) ? $theme['light'] : [];
		$dark  = isset( $theme['dark'] ) && is_array( $theme['dark'] ) ? $theme['dark'] : [];

		$to_block = static function ( array $palette ): string {
			$decls = '';
			foreach ( $palette as $var => $value ) {
				$decls .= sprintf( '%s:%s;', $var, $value );
			}
			return $decls;
		};

		// Emit at the <html> level so portaled UI (WP modals, drawers, react-select
		// menus) — which render outside .zaplane-scope — still inherit the variables.
		// `.zaplane-force-light` pins the light palette for subtrees that must stay
		// light regardless of mode (e.g. the third-party email builder, which has no
		// dark mode and hardcodes its colors inline).
		return sprintf(
			'html[data-theme="light"]{%1$s}html[data-theme="dark"]{%2$s}.zaplane-force-light{%1$s}',
			$to_block( $light ),
			$to_block( $dark )
		);
	}

	/**
	 * The integrations catalogue as a JSON string for inline injection.
	 *
	 * When no Custom Apps exist (the common case) the pre-built static file is
	 * streamed through verbatim — no decode/re-encode. Only when custom apps are
	 * present do we pay the decode + live merge + re-encode.
	 */
	private function get_frontend_integrations_json(): string {
		$file = ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json';

		if ( empty( \Zaplane\CustomApps\ManifestStore::all() ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
			$raw = str_replace( \Zaplane\Framework\Core\IntegrationManifest::REST_URL_TOKEN, rest_url(), $raw );
			return '' !== trim( $raw ) ? $raw : '{"apps":{},"tools":{}}';
		}

		return (string) wp_json_encode( $this->get_frontend_integrations() );
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
		$integrations = [
			'apps' => [],
			'tools' => []
		];

		$file = ZAPLANE_ROOT_DIR_PATH . 'assets/json/integrations.json';
		if ( is_readable( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$decoded = json_decode(
				str_replace(
					\Zaplane\Framework\Core\IntegrationManifest::REST_URL_TOKEN,
					rest_url(),
					(string) file_get_contents( $file )
				),
				true
			);
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
