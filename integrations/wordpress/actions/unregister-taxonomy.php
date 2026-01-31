<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UnregisterTaxonomy extends BaseAction {

    public static function get_label(): string {
        return 'Unregister Taxonomy';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'taxonomy',
                'label'    => 'Taxonomy',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'taxonomies',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'taxonomy'     => 'string',
            'unregistered' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $taxonomy = $config['taxonomy'] ?? '';

        $result = unregister_taxonomy( $taxonomy );

        return static::success( $input, [
            'taxonomy'     => $taxonomy,
            'unregistered' => $result === true,
        ] );
    }
}
