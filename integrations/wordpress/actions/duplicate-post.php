<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DuplicatePost extends BaseAction {

    public static function get_label(): string {
        return 'Duplicate Post';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_id',
                'label'    => 'ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'post_title',
                'label'    => 'New Title',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'     => 'post_type',
                'label'   => 'Post Type',
                'type'    => 'select',
                'dynamic' => [
                    'integration' => 'wordpress',
                    'query'       => 'post_types',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'original_id' => 'integer',
            'new_id'      => 'integer',
            'new_title'   => 'string',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id     = $config['post_id'] ?? 0;
        $new_title   = $config['post_title'] ?? '';
        $post        = get_post( $post_id );

        if ( ! $post ) {
            throw new \Exception( 'Post not found' );
        }

        $final_title = $new_title ?: $post->post_title . ' (copy)';

        $new_post_id = wp_insert_post( [
            'post_type'    => $post->post_type,
            'post_title'   => $final_title,
            'post_content' => $post->post_content,
            'post_status'  => 'draft',
            'post_author'  => $post->post_author,
        ], true );

        if ( is_wp_error( $new_post_id ) ) {
            throw new \Exception( $new_post_id->get_error_message() );
        }

        // Copy taxonomies
        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
            wp_set_object_terms( $new_post_id, $terms, $taxonomy );
        }

        // Copy meta
        $meta = get_post_meta( $post_id );
        foreach ( $meta as $key => $values ) {
            foreach ( $values as $value ) {
                add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
            }
        }

        return static::success( $input, [
            'original_id' => $post_id,
            'new_id'      => $new_post_id,
            'new_title'   => $final_title,
        ] );
    }
}
