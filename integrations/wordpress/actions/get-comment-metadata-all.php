<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetCommentMetadataAll extends BaseAction {

    public static function get_label(): string {
        return 'Get Comment Metadata (All)';
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
            'metadata'   => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $comment_id = $config['comment_id'] ?? 0;
        $metadata   = get_comment_meta( $comment_id );

        return static::success( $input, [
            'comment_id' => $comment_id,
            'metadata'   => $metadata ?: [],
        ] );
    }
}
