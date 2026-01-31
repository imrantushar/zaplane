<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class AddMediaImage extends BaseAction {

    public static function get_label(): string {
        return 'Add New Image';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'image_url',
                'label'    => 'Image URL',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'image_title',
                'label'    => 'Image Title',
                'type'     => 'text',
                'required' => false,
            ],
            [
                'key'      => 'alternative_text',
                'label'    => 'Alternative Text',
                'type'     => 'text',
                'required' => false,
            ],
            [
                'key'      => 'caption',
                'label'    => 'Caption',
                'type'     => 'textarea',
                'required' => false,
            ],
            [
                'key'      => 'description',
                'label'    => 'Description',
                'type'     => 'textarea',
                'required' => false,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'attachment_id' => 'integer',
            'url'           => 'string',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_url = $config['image_url'] ?? '';

        if ( empty( $image_url ) ) {
            throw new \Exception( 'Image URL is required' );
        }

        $attachment_id = media_sideload_image( $image_url, 0, $config['image_title'] ?? '', 'id' );

        if ( is_wp_error( $attachment_id ) ) {
            throw new \Exception( $attachment_id->get_error_message() );
        }

        if ( ! empty( $config['alternative_text'] ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', $config['alternative_text'] );
        }

        if ( ! empty( $config['caption'] ) || ! empty( $config['description'] ) ) {
            wp_update_post( [
                'ID'           => $attachment_id,
                'post_excerpt' => $config['caption'] ?? '',
                'post_content' => $config['description'] ?? '',
            ] );
        }

        return static::success( $input, [
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
        ] );
    }
}
