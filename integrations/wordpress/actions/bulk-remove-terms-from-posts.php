<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class BulkRemoveTermsFromPosts extends BaseAction {

    public static function get_label(): string {
        return 'Bulk Remove Terms from Posts';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_ids',
                'label'    => 'Post IDs (comma-separated)',
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
                'key'      => 'terms',
                'label'    => 'Term IDs (comma-separated)',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'updated_count' => 'integer',
            'post_ids'      => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_ids = array_map( 'intval', array_map( 'trim', explode( ',', $config['post_ids'] ?? '' ) ) );
        $taxonomy = $config['taxonomy'] ?? '';
        $terms    = array_map( 'intval', array_map( 'trim', explode( ',', $config['terms'] ?? '' ) ) );

        $updated = 0;
        foreach ( $post_ids as $post_id ) {
            $result = wp_remove_object_terms( $post_id, $terms, $taxonomy );
            if ( $result === true ) {
                $updated++;
            }
        }

        return static::success( $input, [
            'updated_count' => $updated,
            'post_ids'      => $post_ids,
        ] );
    }
}
