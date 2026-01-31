<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class ReplyComment extends BaseAction {

    public static function get_label(): string {
        return 'Reply To Comment';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'parent_id',
                'label'    => 'Parent Comment ID',
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
                'label'    => 'Reply Content',
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
        $parent = get_comment( $config['parent_id'] ?? 0 );

        if ( ! $parent ) {
            throw new \Exception( 'Parent comment not found' );
        }

        $comment_id = wp_insert_comment( [
            'comment_post_ID'      => $parent->comment_post_ID,
            'comment_author'       => $config['author_name'] ?? '',
            'comment_author_email' => $config['author_email'] ?? '',
            'comment_content'      => $config['content'] ?? '',
            'comment_parent'       => $config['parent_id'],
            'comment_approved'     => 1,
        ] );

        if ( ! $comment_id ) {
            throw new \Exception( 'Failed to create reply' );
        }

        return static::success( $input, [ 'comment_id' => $comment_id ] );
    }
}
