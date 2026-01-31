<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class MarkCommentSpam extends BaseAction {

    public static function get_label(): string {
        return 'Mark Comment as Spam';
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
            'marked'     => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $result = wp_spam_comment( $config['comment_id'] ?? 0 );

        if ( ! $result ) {
            throw new \Exception( 'Failed to mark comment as spam' );
        }

        return static::success( $input, [
            'comment_id' => $config['comment_id'],
            'marked'     => true,
        ] );
    }
}
