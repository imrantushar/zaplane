<?php
namespace Zaplane\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Classes\IntegrationBase;


class Pause extends IntegrationBase {

    public static function get_slug(): string {
        return 'pause';
    }

    public static function execute_node( array $node, array $input ): array {
        global $wpdb;

        $delay = (int) ($node['config']['delay'] ?? 0);

        $wpdb->update(
            $wpdb->prefix . 'zaplane_runs',
            [
                'status'    => 'paused',
                'resume_at' => date( 'Y-m-d H:i:s', time() + $delay ),
            ],
            [ 'id' => $input['_run_id'] ]
        );

        return []; // stop execution
    }
}
