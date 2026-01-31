<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class TrashPost extends BaseAction {

    public static function get_label(): string {
        return 'Trash Post / Page';
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
                'key'      => 'post_type',
                'label'    => 'Post Type',
                'type'     => 'select',
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'post_types',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $result = wp_trash_post( $config['post_id'] ?? 0 );

        if ( ! $result ) {
            throw new \Exception( 'Failed to trash post' );
        }

        return static::success( $input, [ 'post_id' => $config['post_id'] ] );
    }
}
