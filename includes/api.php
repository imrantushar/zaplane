<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API { 
    public static function init(){
        register_rest_route( 'zaplane/v1', '/runs/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => function ( $req ) {
                global $wpdb;
                return $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}zaplane_run_logs WHERE run_id = %d",
                        $req['id']
                    )
                );
            }
        ]);
    }
}


