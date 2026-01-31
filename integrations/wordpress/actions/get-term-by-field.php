<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class GetTermByField extends BaseAction {

    public static function get_label(): string {
        return 'Get Term by Field';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'field',
                'label'    => 'Field',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    [ 'label' => 'ID', 'value' => 'id' ],
                    [ 'label' => 'Slug', 'value' => 'slug' ],
                    [ 'label' => 'Name', 'value' => 'name' ],
                    [ 'label' => 'Term Taxonomy ID', 'value' => 'term_taxonomy_id' ],
                ],
            ],
            [
                'key'      => 'value',
                'label'    => 'Value',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'taxonomy',
                'label'    => 'Taxonomy',
                'type'     => 'select',
                'required' => true,
                'dynamic'  => [
                    'integration' => 'wordpress',
                    'query'       => 'taxonomies',
                    'select'      => [ 'name', 'label' ],
                ],
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'term' => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $field    = $config['field'] ?? 'id';
        $value    = $config['value'] ?? '';
        $taxonomy = $config['taxonomy'] ?? '';

        $term = get_term_by( $field, $value, $taxonomy );

        if ( ! $term ) {
            return static::success( $input, [ 'term' => null ] );
        }

        return static::success( $input, [
            'term' => WordpressPayloadHelpers::resolve_term( $term->term_id, $term->taxonomy, $term->term_taxonomy_id ),
        ] );
    }
}
