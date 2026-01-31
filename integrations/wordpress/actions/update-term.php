<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class UpdateTerm extends BaseAction {

    public static function get_label(): string {
        return 'Update Term';
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
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'taxonomies',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
            [
                'key'      => 'name',
                'label'    => 'New Name',
                'type'     => 'text',
                'required' => false,
            ],
            [
                'key'      => 'slug',
                'label'    => 'New Slug',
                'type'     => 'text',
                'required' => false,
            ],
            [
                'key'      => 'description',
                'label'    => 'New Description',
                'type'     => 'textarea',
                'required' => false,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'term_id' => 'integer',
            'term'    => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $term_id  = $config['term_id'] ?? 0;
        $taxonomy = $config['taxonomy'] ?? '';

        $args = [];
        if ( ! empty( $config['name'] ) ) {
            $args['name'] = $config['name'];
        }
        if ( ! empty( $config['slug'] ) ) {
            $args['slug'] = $config['slug'];
        }
        if ( isset( $config['description'] ) ) {
            $args['description'] = $config['description'];
        }

        $result = wp_update_term( $term_id, $taxonomy, $args );

        if ( is_wp_error( $result ) ) {
            throw new \Exception( $result->get_error_message() );
        }

        return static::success( $input, [
            'term_id' => $result['term_id'],
            'term'    => WordpressPayloadHelpers::resolve_term( $result['term_id'], $taxonomy, $result['term_taxonomy_id'] ),
        ] );
    }
}
