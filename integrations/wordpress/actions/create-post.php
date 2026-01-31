<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class CreatePost extends BaseAction {

    public static function get_label(): string {
        return 'Create Post';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_title',
                'label'    => 'Title',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'   => 'post_content',
                'label' => 'Content',
                'type'  => 'textarea',
            ],
            [
                'key'      => 'post_type',
                'label'    => 'Post Type',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'post_types',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
            [
                'key'     => 'post_status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Draft',   'value' => 'draft' ],
                    [ 'label' => 'Publish', 'value' => 'publish' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $id = wp_insert_post( [
            'post_title'   => $config['post_title'] ?? '',
            'post_content' => $config['post_content'] ?? '',
            'post_status'  => $config['post_status'] ?? 'draft',
            'post_type'    => $config['post_type'] ?? 'post',
        ] );

        if ( is_wp_error( $id ) ) {
            throw new \Exception( $id->get_error_message() );
        }

        return static::success( $input, [ 'post_id' => $id ] );
    }
}
