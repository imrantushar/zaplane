<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetPostsAll extends BaseAction {

    public static function get_label(): string {
        return 'Get Post (All)';
    }

    public static function get_config_schema(): array {
        return [];
    }

    public static function get_output_schema(): array {
        return [
            'posts' => 'array',
            'count' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $posts = get_posts( [
            'post_type'      => 'any',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ] );

        $result = [];
        foreach ( $posts as $post ) {
            $result[] = [
                'ID'          => $post->ID,
                'post_title'  => $post->post_title,
                'post_type'   => $post->post_type,
                'post_status' => $post->post_status,
                'post_date'   => $post->post_date,
            ];
        }

        return static::success( $input, [
            'posts' => $result,
            'count' => count( $result ),
        ] );
    }
}
