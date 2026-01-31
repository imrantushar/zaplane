<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeleteComment extends BaseAction {

    public static function get_label(): string {
        return 'Delete Comment';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'comment_id',
                'label'    => 'Comment ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'comment_id' => 'integer',
            'deleted'    => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $result = wp_delete_comment( $config['comment_id'] ?? 0, true );

        if ( ! $result ) {
            throw new \Exception( 'Failed to delete comment' );
        }

        return static::success( $input, [
            'comment_id' => $config['comment_id'],
            'deleted'    => true,
        ] );
    }
}
