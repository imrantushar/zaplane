<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateCommentCount extends BaseAction {

    public static function get_label(): string {
        return 'Update Comment Count';
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
            'post_id' => 'integer',
            'updated' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id = $config['post_id'] ?? 0;
        $result  = wp_update_comment_count( $post_id );

        return static::success( $input, [
            'post_id' => $post_id,
            'updated' => (bool) $result,
        ] );
    }
}
