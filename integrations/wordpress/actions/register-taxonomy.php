<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class RegisterTaxonomy extends BaseAction {

    public static function get_label(): string {
        return 'Register Taxonomy';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'slug',
                'label'    => 'Taxonomy Slug',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'label',
                'label'    => 'Label',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'post_types',
                'label'    => 'Post Types (comma-separated)',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'hierarchical',
                'label'    => 'Hierarchical (like Categories)',
                'type'     => 'boolean',
                'required' => false,
                'default'  => false,
            ],
            [
                'key'      => 'public',
                'label'    => 'Public',
                'type'     => 'boolean',
                'required' => false,
                'default'  => true,
            ],
            [
                'key'      => 'show_in_rest',
                'label'    => 'Show in REST API',
                'type'     => 'boolean',
                'required' => false,
                'default'  => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'slug'       => 'string',
            'registered' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $slug          = $config['slug'] ?? '';
        $label         = $config['label'] ?? '';
        $post_types    = array_map( 'trim', explode( ',', $config['post_types'] ?? '' ) );
        $hierarchical  = $config['hierarchical'] ?? false;
        $public        = $config['public'] ?? true;
        $show_in_rest  = $config['show_in_rest'] ?? true;

        $result = register_taxonomy( $slug, $post_types, [
            'label'        => $label,
            'hierarchical' => $hierarchical,
            'public'       => $public,
            'show_in_rest' => $show_in_rest,
        ] );

        return static::success( $input, [
            'slug'       => $slug,
            'registered' => ! is_wp_error( $result ),
        ] );
    }
}
