<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class GetTermsByTaxonomy extends BaseAction {

    public static function get_label(): string {
        return 'Get Terms by Taxonomy';
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
            [
                'key'      => 'hide_empty',
                'label'    => 'Hide Empty',
                'type'     => 'boolean',
                'required' => false,
                'default'  => false,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'taxonomy' => 'string',
            'terms'    => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $taxonomy   = $config['taxonomy'] ?? '';
        $hide_empty = $config['hide_empty'] ?? false;

        $terms = get_terms( [
            'taxonomy'   => $taxonomy,
            'hide_empty' => $hide_empty,
        ] );

        if ( is_wp_error( $terms ) ) {
            return static::success( $input, [ 'taxonomy' => $taxonomy, 'terms' => [] ] );
        }

        $resolved = [];
        foreach ( $terms as $term ) {
            $resolved[] = WordpressPayloadHelpers::resolve_term( $term->term_id, $term->taxonomy, $term->term_taxonomy_id );
        }

        return static::success( $input, [
            'taxonomy' => $taxonomy,
            'terms'    => $resolved,
        ] );
    }
}
