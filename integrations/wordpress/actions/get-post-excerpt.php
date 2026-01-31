<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetPostExcerpt extends BaseAction {

    public static function get_label(): string {
        return 'Get Post Excerpt';
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
            'post_id'      => 'integer',
            'post_excerpt' => 'string',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id = $config['post_id'] ?? 0;
        $post    = get_post( $post_id );

        if ( ! $post ) {
            throw new \Exception( 'No post found for this ID' );
        }

        return static::success( $input, [
            'post_id'      => $post_id,
            'post_excerpt' => $post->post_excerpt,
        ] );
    }
}
