<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class CreateComment extends BaseAction {

    public static function get_label(): string {
        return 'Create New Comment';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_id',
                'label'    => 'ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'author_name',
                'label'    => 'Author Name',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'author_email',
                'label'    => 'Author Email',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'content',
                'label'    => 'Comment Content',
                'type'     => 'textarea',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'comment_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $comment_id = wp_insert_comment( [
            'comment_post_ID'      => $config['post_id'] ?? 0,
            'comment_author'       => $config['author_name'] ?? '',
            'comment_author_email' => $config['author_email'] ?? '',
            'comment_content'      => $config['content'] ?? '',
            'comment_approved'     => 1,
        ] );

        if ( ! $comment_id ) {
            throw new \Exception( 'Failed to create comment' );
        }

        return static::success( $input, [ 'comment_id' => $comment_id ] );
    }
}
