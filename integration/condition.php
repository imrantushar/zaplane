<?php
namespace Zaplane\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Classes\IntegrationBase;

class Condition extends IntegrationBase {

    public static function get_slug(): string {
        return 'condition';
    }

    public static function get_output_ports(): array {
        return [ 'true', 'false' ];
    }

    public static function execute_node( array $node, array $input ): array {
        $expr = $node['config']['expression'] ?? '';
        $result = \Expression::evaluate( $expr, $input );

        return [
            'port' => $result ? 'true' : 'false',
            'data' => $input,
        ];
    }

    
}