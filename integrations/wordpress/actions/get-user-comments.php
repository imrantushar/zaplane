<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetUserComments extends BaseAction {

    public static function get_label(): string {
        return 'Get User Comments';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'user_id',
                'label'    => 'User ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id'  => 'integer',
            'comments' => 'array',
            'count'    => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id  = $config['user_id'] ?? 0;
        $comments = get_comments( [
            'user_id' => $user_id,
            'status'  => 'all',
        ] );

        $result = [];
        foreach ( $comments as $comment ) {
            $result[] = [
                'comment_ID'           => $comment->comment_ID,
                'comment_post_ID'      => $comment->comment_post_ID,
                'comment_author'       => $comment->comment_author,
                'comment_author_email' => $comment->comment_author_email,
                'comment_author_url'   => $comment->comment_author_url,
                'comment_date'         => $comment->comment_date,
                'comment_content'      => $comment->comment_content,
                'comment_approved'     => $comment->comment_approved,
                'comment_parent'       => $comment->comment_parent,
                'user_id'              => $comment->user_id,
            ];
        }

        return static::success( $input, [
            'user_id'  => $user_id,
            'comments' => $result,
            'count'    => count( $result ),
        ] );
    }
}
