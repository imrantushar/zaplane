<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetTaxonomies extends BaseAction {

    public static function get_label(): string {
        return 'Get Taxonomies (All)';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'public',
                'label'    => 'Public Only',
                'type'     => 'boolean',
                'required' => false,
                'default'  => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'taxonomies' => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $public = $config['public'] ?? true;

        $args = [];
        if ( $public ) {
            $args['public'] = true;
        }

        $taxonomies = get_taxonomies( $args, 'objects' );

        $result = [];
        foreach ( $taxonomies as $tax ) {
            $result[] = [
                'name'         => $tax->name,
                'label'        => $tax->label,
                'hierarchical' => $tax->hierarchical,
                'public'       => $tax->public,
                'object_type'  => $tax->object_type,
            ];
        }

        return static::success( $input, [
            'taxonomies' => $result,
        ] );
    }
}
