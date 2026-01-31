<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetUserCaps extends BaseAction {

    public static function get_label(): string {
        return 'Get User Capabilities';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'user_id',
                'label'    => 'User ID',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id'      => 'integer',
            'capabilities' => 'array',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id = $config['user_id'] ?? 0;

        $user = get_userdata( $user_id );
        $caps = $user ? array_keys( array_filter( $user->allcaps ) ) : [];

        return static::success( $input, [
            'user_id'      => $user_id,
            'capabilities' => $caps,
        ] );
    }
}
