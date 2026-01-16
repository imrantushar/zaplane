<?php
namespace Zaplane\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Classes\IntegrationBase;

class Iterator extends IntegrationBase {

    public static function get_slug(): string {
        return 'iterator';
    }

    public static function get_category(): string {
        return 'tool';
    }

    public static function get_output_ports(): array {
        return [ 'loop', 'done' ];
    }

    public static function execute_node( array $node, array $input ): array {

        $items = $input[ $node['config']['source'] ] ?? [];

        if ( empty( $items ) ) {
            return [ 'port' => 'done', 'data' => $input ];
        }

        $current = array_shift( $items );

        return [
            'port' => 'loop',
            'data' => array_merge( $input, [
                'item' => $current,
                '_remaining' => $items,
            ]),
        ];
    }
}
