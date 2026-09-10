<?php
namespace Zaplane\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Utils\Helper;

class Menu {

	public function register() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}
	public function admin_menu() {
		$icon_url = $this->get_toplevel_menu_icon_url();
		$page_title = $this->get_toplevel_menu_title();
		add_menu_page( $page_title, $page_title, 'manage_options', ZAPLANE_PLUGIN_SLUG, [ $this, 'load_main_template' ], $icon_url, 2 );
		foreach ( Helper::get_admin_menu_list() as $item_key => $item ) {
			add_submenu_page( $item['parent_slug'], $item['title'], $item['title'], $item['capability'], $item_key, [ $this, 'load_main_template' ] );
		}
	}
	/**
	 * The icon beside "Zaplane" in the admin menu.
	 *
	 * WordPress repaints these itself. svg-painter.js decodes the data URI and
	 * rewrites every `fill` with the colour the current admin scheme gives the
	 * menu — one for the resting state, one for the open one, one for hover — so
	 * whatever the file says the fill is, it is not what renders.
	 *
	 * The old icon was drawn two-tone: a disc filled one colour with the mark
	 * stroked in another. Core rewrites fills and leaves strokes alone, so the
	 * disc went grey with the scheme while the mark stayed near-black, and the
	 * whole thing read as a smudge beside a column of clean white icons.
	 *
	 * `menu-icon-mono.svg` is one shape with the mark cut out of it, so there is
	 * a single fill for core to repaint and the cut-out shows the menu through.
	 * That is why both states are handed the same file: core is already drawing
	 * the difference. Both filters stay, because both are published.
	 */
	public function get_toplevel_menu_icon_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading which admin screen is open to pick an icon; nothing is acted on.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$is_zaplane_page = ( ZAPLANE_PLUGIN_SLUG === $current_page || 0 === strpos( $current_page, ZAPLANE_PLUGIN_SLUG . '-' ) );

		if ( $is_zaplane_page ) {
			return apply_filters( 'zaplane/admin/toplevel_active_menu_icon', self::icon( 'menu-icon-mono.svg' ) );
		}

		return apply_filters( 'zaplane/admin/toplevel_inactive_menu_icon', self::icon( 'menu-icon-mono.svg' ) );
	}

	/**
	 * One icon file as a data URI.
	 *
	 * No space after the comma: the data begins immediately, and a stray one is
	 * only tolerated rather than allowed.
	 *
	 * @param string $file A filename in assets/images.
	 */
	private static function icon( string $file ): string {
		$path = ZAPLANE_ASSETS_DIR_PATH . 'images/' . $file;

		if ( ! is_readable( $path ) ) {
			// Core draws its own default rather than a broken image.
			return 'dashicons-admin-generic';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Reading a file this plugin ships, for a data URI that has to be base64.
		return 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( $path ) );
	}
	public function get_toplevel_menu_title() {
		return apply_filters( 'zaplane/admin/toplevel_menu_title', __( 'Zaplane', 'zaplane' ) );
	}
	public function load_main_template() {
		echo '<div id="zaplane-app" class="zaplane-app">Loading...</div>';
	}
}
