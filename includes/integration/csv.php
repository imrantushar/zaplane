<?php
namespace Zaplane\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Classes\IntegrationBase;

class Csv extends IntegrationBase {

    public static function get_slug(): string {
        return 'csv';
    }

    public static function execute_node( array $node, array $input ): array {
        $rows = array_map( 'str_getcsv', file( $node['config']['path'] ) );

        return [
            'port' => 'main',
            'data' => array_merge( $input, [ 'rows' => $rows ] ),
        ];
    }
}
