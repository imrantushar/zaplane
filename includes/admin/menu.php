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
		add_menu_page( $page_title, $page_title . $this->pending_bubble(), 'manage_options', ZAPLANE_PLUGIN_SLUG, [ $this, 'load_main_template' ], $icon_url, 2 );
		foreach ( Helper::get_admin_menu_list() as $item_key => $item ) {
			add_submenu_page( $item['parent_slug'], $item['title'], $item['title'], $item['capability'], $item_key, [ $this, 'load_main_template' ] );
		}
	}
	/**
	 * The count bubble core uses for pending comments and plugin updates.
	 *
	 * Someone waiting to be let in has to be visible from wherever the
	 * administrator happens to be, and an admin notice is not dependable for
	 * that: plugins remove them wholesale, and Zaplane's own screens do too so
	 * the app is not framed by other people's banners. The menu survives all of
	 * that.
	 */
	private function pending_bubble(): string {
		if ( ! \Zaplane\Settings::feature_enabled( 'mcp_server' ) ) {
			return '';
		}

		$count = \Zaplane\Mcp\OAuth\PendingStore::waiting_count();

		if ( 0 === $count ) {
			return '';
		}

		return sprintf(
			' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>',
			$count
		);
	}

	public function get_toplevel_menu_icon_url() {
        // phpcs:disable
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        $is_zaplane_page = ( $current_page === ZAPLANE_PLUGIN_SLUG || strpos( $current_page, ZAPLANE_PLUGIN_SLUG . '-' ) === 0 );
        if ( $is_zaplane_page ) {
            $icon_url = 'data:image/svg+xml;base64, ' . base64_encode(file_get_contents(ZAPLANE_ASSETS_DIR_PATH . 'images/menu-icon.svg'));
            return apply_filters('zaplane/admin/toplevel_active_menu_icon', $icon_url);
        }
        $icon_url = 'data:image/svg+xml;base64, ' . base64_encode(file_get_contents(ZAPLANE_ASSETS_DIR_PATH . 'images/menu-icon-gray.svg'));
        return apply_filters('zaplane/admin/toplevel_inactive_menu_icon', $icon_url);
    }
	public function get_toplevel_menu_title()
    {
        return apply_filters('zaplane/admin/toplevel_menu_title', __('Zaplane', 'zaplane'));
    }
	public function load_main_template() {
		echo '<div id="zaplane-app" class="zaplane-app">Loading...</div>';
	}
}
