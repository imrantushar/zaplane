<?php
namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper { 
    public static function get_admin_menu_list() {
		$menu                                     = [];
		$menu[ ZAPLANE_PLUGIN_SLUG ]              = [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Dashboard', 'zaplane' ),
			'capability'  => 'manage_options',
		];
		$menu[ ZAPLANE_PLUGIN_SLUG . '-workflows' ] = [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Workflows', 'zaplane' ),
			'capability'  => 'manage_options',
		];
		$menu[ ZAPLANE_PLUGIN_SLUG . '-connections' ] = [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Connections', 'zaplane' ),
			'capability'  => 'manage_options',
		];
		$menu[ ZAPLANE_PLUGIN_SLUG . '-queue' ] = [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Queue', 'zaplane' ),
			'capability'  => 'manage_options',
		];
		$menu[ ZAPLANE_PLUGIN_SLUG . '-logs' ] = [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Logs', 'zaplane' ),
			'capability'  => 'manage_options',
		];
		$menu[ ZAPLANE_PLUGIN_SLUG . '-settings' ] = [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Settings', 'zaplane' ),
			'capability'  => 'manage_options',
		];

		return apply_filters( 'zaplane/admin_menu_list', $menu );
	}
}