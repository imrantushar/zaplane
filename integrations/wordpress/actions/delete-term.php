<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeleteTerm extends BaseAction {

    public static function get_label(): string {
        return 'Delete Term';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'term_id',
                'label'    => 'Term ID',
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
            'term_id' => 'integer',
            'deleted' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $term_id  = $config['term_id'] ?? 0;
        $taxonomy = $config['taxonomy'] ?? '';

        $result = wp_delete_term( $term_id, $taxonomy );

        return static::success( $input, [
            'term_id' => $term_id,
            'deleted' => $result === true,
        ] );
    }
}
