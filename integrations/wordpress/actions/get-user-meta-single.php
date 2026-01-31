<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetUserMetaSingle extends BaseAction {

    public static function get_label(): string {
        return 'Get User Metadata (Single)';
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
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id'  => 'integer',
            'meta_key' => 'string',
            'value'    => 'mixed',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id  = $config['user_id'] ?? 0;
        $meta_key = $config['meta_key'] ?? '';
        $value    = get_user_meta( $user_id, $meta_key, true );

        return static::success( $input, [
            'user_id'  => $user_id,
            'meta_key' => $meta_key,
            'value'    => $value,
        ] );
    }
}
