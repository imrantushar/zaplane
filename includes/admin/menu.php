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
	public function get_toplevel_menu_icon_url() {
        // phpcs:disable
        if (isset($_GET['page']) && 'zaplane' === $_GET['page']) {
            $icon_url = 'data:image/svg+xml;base64, ' . base64_encode(file_get_contents(ZAPLANE_ASSETS_DIR_PATH . 'images/menu-icon.svg'));
            return apply_filters('zaplane/admin/toplevel_active_menu_icon', $icon_url);
        }
        $icon_url = 'data:image/svg+xml;base64, ' . base64_encode(file_get_contents(ZAPLANE_ASSETS_DIR_PATH . 'images/menu-icon.svg'));
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
