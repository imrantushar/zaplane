<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateTitle extends BaseAction {

    public static function get_label(): string {
        return 'Update Title';
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
                'key'      => 'post_title',
                'label'    => 'New Title',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'post_type',
                'label'    => 'Post Type',
                'type'     => 'select',
                'required' => true,
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
        $id = wp_update_post( [
            'ID'         => $config['post_id'],
            'post_title' => $config['post_title'],
        ] );

        if ( is_wp_error( $id ) ) {
            throw new \Exception( $id->get_error_message() );
        }

        return static::success( $input, [ 'post_id' => $id ] );
    }
}
