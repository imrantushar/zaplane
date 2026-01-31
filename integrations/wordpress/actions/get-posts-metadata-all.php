<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetPostsMetadataAll extends BaseAction {

    public static function get_label(): string {
        return 'Get Post Metadata (All)';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_id',
                'label'    => 'ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id'  => 'integer',
            'metadata' => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id  = $config['post_id'] ?? 0;
        $metadata = get_post_meta( $post_id );

        $result = [];
        foreach ( $metadata as $key => $values ) {
            $result[ $key ] = maybe_unserialize( $values[0] ?? '' );
        }

        return static::success( $input, [
            'post_id'  => $post_id,
            'metadata' => $result,
        ] );
    }
}
