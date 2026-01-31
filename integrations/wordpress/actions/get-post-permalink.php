<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetPostPermalink extends BaseAction {

    public static function get_label(): string {
        return 'Get Post Permalink';
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
            'post_id'   => 'integer',
            'permalink' => 'string',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id   = $config['post_id'] ?? 0;
        $permalink = get_permalink( $post_id );

        if ( ! $permalink ) {
            throw new \Exception( 'No permalink found for this post' );
        }

        return static::success( $input, [
            'post_id'   => $post_id,
            'permalink' => $permalink,
        ] );
    }
}
