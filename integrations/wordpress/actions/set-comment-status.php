<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class SetCommentStatus extends BaseAction {

    public static function get_label(): string {
        return 'Set Comment Status';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'comment_id',
                'label'    => 'Comment ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'status',
                'label'    => 'Status',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    [ 'label' => 'Approved', 'value' => '1' ],
                    [ 'label' => 'Pending',  'value' => '0' ],
                    [ 'label' => 'Spam',     'value' => 'spam' ],
                    [ 'label' => 'Trash',    'value' => 'trash' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'comment_id' => 'integer',
            'status'     => 'string',
            'updated'    => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $comment_id = $config['comment_id'] ?? 0;
        $status     = $config['status'] ?? '0';
        $result     = wp_set_comment_status( $comment_id, $status );

        if ( ! $result ) {
            throw new \Exception( 'Failed to set comment status' );
        }

        return static::success( $input, [
            'comment_id' => $comment_id,
            'status'     => $status,
            'updated'    => true,
        ] );
    }
}
