<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeactivateUser extends BaseAction {

    public static function get_label(): string {
        return 'Deactivate User';
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
            'user_id'     => 'integer',
            'deactivated' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id = $config['user_id'] ?? 0;

        // Mark user as deactivated using user meta
        update_user_meta( $user_id, 'zaplane_user_deactivated', true );

        return static::success( $input, [
            'user_id'     => $user_id,
            'deactivated' => true,
        ] );
    }
}
