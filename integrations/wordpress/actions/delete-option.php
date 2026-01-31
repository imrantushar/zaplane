<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeleteOption extends BaseAction {

    public static function get_label(): string {
        return 'Delete Option';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'option_name',
                'label'    => 'Option',
                'type'     => 'text',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'option_name' => 'string',
            'deleted'     => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $option_name = $config['option_name'] ?? '';
        $result      = delete_option( $option_name );

        return static::success( $input, [
            'option_name' => $option_name,
            'deleted'     => $result,
        ] );
    }
}
