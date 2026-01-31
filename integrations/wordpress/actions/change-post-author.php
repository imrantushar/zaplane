<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class ChangePostAuthor extends BaseAction {

    public static function get_label(): string {
        return 'Change Post Author';
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
                'key'      => 'author_id',
                'label'    => 'Author ID',
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
        $post_id   = $config['post_id'] ?? 0;
        $author_id = $config['author_id'] ?? 0;

        $result = wp_update_post( [
            'ID'          => $post_id,
            'post_author' => $author_id,
        ], true );

        if ( is_wp_error( $result ) ) {
            throw new \Exception( $result->get_error_message() );
        }

        return static::success( $input, [ 'post_id' => $post_id ] );
    }
}
