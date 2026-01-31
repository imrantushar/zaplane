<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class UpdateUserMeta extends BaseAction {

    public static function get_label(): string {
        return 'Update User Metadata';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'user_id',
                'label'    => 'User ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'meta_key',
                'label'    => 'Meta Key',
                'type'     => 'text',
                'required' => true,
            ],
            [
                'key'      => 'meta_value',
                'label'    => 'Meta Value',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id'  => 'integer',
            'meta_key' => 'string',
            'updated'  => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id    = $config['user_id'] ?? 0;
        $meta_key   = $config['meta_key'] ?? '';
        $meta_value = $config['meta_value'] ?? '';

        update_user_meta( $user_id, $meta_key, $meta_value );

        return static::success( $input, [
            'user_id'  => $user_id,
            'meta_key' => $meta_key,
            'updated'  => true,
        ] );
    }
}
