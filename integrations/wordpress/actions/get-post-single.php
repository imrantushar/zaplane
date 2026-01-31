<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetPostSingle extends BaseAction {

    public static function get_label(): string {
        return 'Get Post (Single)';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_id',
                'label'    => 'ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post' => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post = get_post( $config['post_id'] ?? 0 );

        if ( ! $post ) {
            throw new \Exception( 'Post not found' );
        }

        return static::success( $input, [
            'post' => [
                'ID'           => $post->ID,
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_type'    => $post->post_type,
                'post_status'  => $post->post_status,
                'post_date'    => $post->post_date,
                'post_author'  => $post->post_author,
            ],
        ] );
    }
}
