<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UnapprovedComment extends BaseAction {

    public static function get_label(): string {
        return 'Unapproved Comment';
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
            'comment_id'  => 'integer',
            'unapproved'  => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $comment_id = $config['comment_id'] ?? 0;
        $result     = wp_set_comment_status( $comment_id, 'hold' );

        if ( ! $result ) {
            throw new \Exception( 'Failed to unapprove comment' );
        }

        return static::success( $input, [
            'comment_id'  => $comment_id,
            'unapproved'  => true,
        ] );
    }
}
