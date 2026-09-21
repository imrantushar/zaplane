<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
return [
	'admin' => [
		ZAPLANE_PLUGIN_SLUG => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Dashboard', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-inbox' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Inbox', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-workflows' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Workflows', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-connections' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Connections', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-custom-apps' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Custom Apps', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-recipes' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Recipes', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-knowledge' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Business Knowledge', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-email-templates' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Email Templates', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-folders' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Folders', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-logs' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Logs', 'zaplane' ),
			'capability'  => 'manage_options',
		],
		ZAPLANE_PLUGIN_SLUG . '-settings' => [
			'parent_slug' => ZAPLANE_PLUGIN_SLUG,
			'title'       => __( 'Settings', 'zaplane' ),
			'capability'  => 'manage_options',
		],
	],
];
