<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetCommentMetadataSingle extends BaseAction {

    public static function get_label(): string {
        return 'Get Comment Metadata (Single)';
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
                'key'      => 'meta_key',
                'label'    => 'Meta Key',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'comment_id' => 'integer',
            'meta_key'   => 'string',
            'meta_value' => 'mixed',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $comment_id = $config['comment_id'] ?? 0;
        $meta_key   = $config['meta_key'] ?? '';
        $meta_value = get_comment_meta( $comment_id, $meta_key, true );

        return static::success( $input, [
            'comment_id' => $comment_id,
            'meta_key'   => $meta_key,
            'meta_value' => $meta_value,
        ] );
    }
}
