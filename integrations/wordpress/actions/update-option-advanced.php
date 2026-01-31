<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateOptionAdvanced extends BaseAction {

    public static function get_label(): string {
        return 'Update Option';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'option_name',
                'label'    => 'Option Name',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'   => 'value',
                'label' => 'New Value',
                'type'  => 'expression',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'option_name' => 'string',
            'updated'     => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $option_name = $config['option_name'] ?? '';
        $value       = $config['value'] ?? '';

        $result = update_option( $option_name, $value );

        return static::success( $input, [
            'option_name' => $option_name,
            'updated'     => $result,
        ] );
    }
}
