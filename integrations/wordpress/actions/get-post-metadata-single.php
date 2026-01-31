<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetPostMetadataSingle extends BaseAction {

    public static function get_label(): string {
        return 'Get Post Metadata (Single)';
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
                'key'      => 'meta_key',
                'label'    => 'Post Meta Key',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id'    => 'integer',
            'meta_key'   => 'string',
            'meta_value' => 'mixed',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id    = $config['post_id'] ?? 0;
        $meta_key   = $config['meta_key'] ?? '';
        $meta_value = maybe_unserialize( get_post_meta( $post_id, $meta_key, true ) );

        if ( ! $meta_value ) {
            throw new \Exception( 'No meta value found for this key' );
        }

        return static::success( $input, [
            'post_id'    => $post_id,
            'meta_key'   => $meta_key,
            'meta_value' => $meta_value,
        ] );
    }
}
