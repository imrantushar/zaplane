<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class AddTaxonomyToPost extends BaseAction {

    public static function get_label(): string {
        return 'Add Taxonomy to Post';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_id',
                'label'    => 'Post ID',
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
                'key'      => 'terms',
                'label'    => 'Term IDs or Slugs (comma-separated)',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'append',
                'label'    => 'Append (don\'t replace)',
                'type'     => 'boolean',
                'required' => false,
                'default'  => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id' => 'integer',
            'added'   => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id  = $config['post_id'] ?? 0;
        $taxonomy = $config['taxonomy'] ?? '';
        $terms    = array_map( 'trim', explode( ',', $config['terms'] ?? '' ) );
        $append   = $config['append'] ?? true;

        // Convert numeric strings to integers
        $terms = array_map( function( $term ) {
            return is_numeric( $term ) ? (int) $term : $term;
        }, $terms );

        $result = wp_set_object_terms( $post_id, $terms, $taxonomy, $append );

        return static::success( $input, [
            'post_id' => $post_id,
            'added'   => ! is_wp_error( $result ),
        ] );
    }
}
