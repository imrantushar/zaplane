<?php
namespace Zaplane\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu { 
    public static function init(){
        $self = new self();
        add_action( 'admin_menu', array( $self, 'admin_menu' ) );
    }
    public function admin_menu() {
		$icon_url = '';
		$page_title = 'Zaplane';
		add_menu_page( $page_title, $page_title, 'manage_options', ZAPLANE_PLUGIN_SLUG, [ $this, 'load_main_template' ], $icon_url, 2 );
	}
    public function load_main_template(){
		echo '<div id="zaplane-app" class="zaplane-app">Loading...</div>';
    }
}