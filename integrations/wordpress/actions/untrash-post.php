<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UntrashPost extends BaseAction {

    public static function get_label(): string {
        return 'Untrash Post';
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
            'post_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $result = wp_untrash_post( $config['post_id'] ?? 0 );

        if ( ! $result ) {
            throw new \Exception( 'Failed to untrash post' );
        }

        return static::success( $input, [ 'post_id' => $config['post_id'] ] );
    }
}
