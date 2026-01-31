<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class GetTerm extends BaseAction {

    public static function get_label(): string {
        return 'Get Term (Single)';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'term_id',
                'label'    => 'Term ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'taxonomy',
                'label'    => 'Taxonomy',
                'type'     => 'select',
                'required' => false,
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
            'term' => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $term_id  = $config['term_id'] ?? 0;
        $taxonomy = $config['taxonomy'] ?? '';

        $term = get_term( $term_id, $taxonomy );

        if ( is_wp_error( $term ) || ! $term ) {
            return static::success( $input, [ 'term' => null ] );
        }

        return static::success( $input, [
            'term' => WordpressPayloadHelpers::resolve_term( $term->term_id, $term->taxonomy, $term->term_taxonomy_id ),
        ] );
    }
}
