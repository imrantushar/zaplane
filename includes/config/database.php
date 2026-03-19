<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

return [
	'prefix' => $wpdb->prefix,
	'charset' => $wpdb->charset,
	'collate' => $wpdb->collate,

	'tables' => [
		'workflows' => $wpdb->prefix . 'zaplane_workflows',
		'workflow_versions' => $wpdb->prefix . 'zaplane_workflow_versions',
		'runs' => $wpdb->prefix . 'zaplane_runs',
		'node_runs' => $wpdb->prefix . 'zaplane_node_runs',

		'connections' => $wpdb->prefix . 'zaplane_connections',
	],
];
