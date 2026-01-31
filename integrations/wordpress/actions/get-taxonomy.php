<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetTaxonomy extends BaseAction {

    public static function get_label(): string {
        return 'Get Taxonomy (Single)';
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
            'taxonomy' => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $taxonomy = $config['taxonomy'] ?? '';

        $tax = get_taxonomy( $taxonomy );

        if ( ! $tax ) {
            return static::success( $input, [ 'taxonomy' => null ] );
        }

        return static::success( $input, [
            'taxonomy' => [
                'name'         => $tax->name,
                'label'        => $tax->label,
                'hierarchical' => $tax->hierarchical,
                'public'       => $tax->public,
                'object_type'  => $tax->object_type,
                'rewrite'      => $tax->rewrite,
            ],
        ] );
    }
}
