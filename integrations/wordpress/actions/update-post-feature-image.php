<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdatePostFeatureImage extends BaseAction {

    public static function get_label(): string {
        return 'Update Post Featured Image';
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
                'key'      => 'image_id',
                'label'    => 'Featured Image ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id  = $config['post_id'] ?? 0;
        $image_id = $config['image_id'] ?? 0;

        if ( $post_id && $image_id ) {
            set_post_thumbnail( $post_id, $image_id );
        }

        return static::success( $input, [ 'post_id' => $post_id ] );
    }
}
