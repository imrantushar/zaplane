<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class TrashComment extends BaseAction {

    public static function get_label(): string {
        return 'Trash Comment';
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
            'trashed'    => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $result = wp_trash_comment( $config['comment_id'] ?? 0 );

        if ( ! $result ) {
            throw new \Exception( 'Failed to trash comment' );
        }

        return static::success( $input, [
            'comment_id' => $config['comment_id'],
            'trashed'    => true,
        ] );
    }
}
