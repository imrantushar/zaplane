<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class RemoveTaxonomyFromPost extends BaseAction {

    public static function get_label(): string {
        return 'Remove Taxonomy from Post';
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
                'label'    => 'Term IDs (comma-separated)',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id' => 'integer',
            'removed' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id  = $config['post_id'] ?? 0;
        $taxonomy = $config['taxonomy'] ?? '';
        $terms    = array_map( 'intval', array_map( 'trim', explode( ',', $config['terms'] ?? '' ) ) );

        $result = wp_remove_object_terms( $post_id, $terms, $taxonomy );

        return static::success( $input, [
            'post_id' => $post_id,
            'removed' => $result === true,
        ] );
    }
}
