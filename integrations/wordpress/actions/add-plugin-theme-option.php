<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class AddPluginThemeOption extends BaseAction {

    public static function get_label(): string {
        return 'Add Option';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'option_name',
                'label'    => 'Option',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'   => 'value',
                'label' => 'Value',
                'type'  => 'expression',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'option_name' => 'string',
            'added'       => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $option_name = $config['option_name'] ?? '';
        $value       = $config['value'] ?? '';

        $result = add_option( $option_name, $value );

        return static::success( $input, [
            'option_name' => $option_name,
            'added'       => $result,
        ] );
    }
}
