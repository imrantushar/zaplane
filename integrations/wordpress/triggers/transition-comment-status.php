<?php

namespace Zaplane\Integrations\Wordpress\Triggers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseTrigger;

class TransitionCommentStatus extends BaseTrigger {

    public static function get_label(): string {
        return 'Comment Status Changed';
    }

    public static function get_hook(): string {
        return 'transition_comment_status';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'     => 'from_status',
                'label'   => 'From Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Approved', 'value' => '1' ],
                    [ 'label' => 'Pending',  'value' => '0' ],
                    [ 'label' => 'Spam',     'value' => 'spam' ],
                    [ 'label' => 'Trash',    'value' => 'trash' ],
                ],
            ],
            [
                'key'     => 'to_status',
                'label'   => 'To Status',
                'type'    => 'select',
                'options' => [
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
            'post_id'    => 'integer',
            'old_status' => 'string',
            'new_status' => 'string',
            'content'    => 'string',
        ];
    }

    public static function resolve( array $node, array $hook_args ) {
        $comment = get_comment( $hook_args[1] ?? 0 );

        if ( ! $comment ) {
            return false;
        }

        return [
            'comment_id' => $comment->comment_ID,
            'post_id'    => $comment->comment_post_ID,
            'old_status' => $hook_args[2] ?? '',
            'new_status' => $hook_args[0] ?? '',
            'content'    => $comment->comment_content,
        ];
    }
}
