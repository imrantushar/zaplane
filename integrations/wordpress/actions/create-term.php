<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class CreateTerm extends BaseAction {

    public static function get_label(): string {
        return 'Create New Term';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'term_name',
                'label'    => 'Term Name',
                'type'     => 'text',
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
                'key'   => 'slug',
                'label' => 'Slug',
                'type'  => 'text',
            ],
            [
                'key'   => 'description',
                'label' => 'Description',
                'type'  => 'textarea',
            ],
            [
                'key'   => 'parent',
                'label' => 'Parent Term ID',
                'type'  => 'expression',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'term_id'          => 'integer',
            'term_taxonomy_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $args = [];

        if ( ! empty( $config['slug'] ) ) {
            $args['slug'] = $config['slug'];
        }
        if ( ! empty( $config['description'] ) ) {
            $args['description'] = $config['description'];
        }
        if ( ! empty( $config['parent'] ) ) {
            $args['parent'] = (int) $config['parent'];
        }

        $result = wp_insert_term(
            $config['term_name'] ?? '',
            $config['taxonomy'] ?? '',
            $args
        );

        if ( is_wp_error( $result ) ) {
            throw new \Exception( $result->get_error_message() );
        }

        return static::success( $input, [
            'term_id'          => $result['term_id'],
            'term_taxonomy_id' => $result['term_taxonomy_id'],
        ] );
    }
}
