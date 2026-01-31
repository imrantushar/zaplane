<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class ActivateUser extends BaseAction {

    public static function get_label(): string {
        return 'Activate User';
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
            'user_id'   => 'integer',
            'activated' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id = $config['user_id'] ?? 0;

        // Remove deactivated flag
        delete_user_meta( $user_id, 'zaplane_user_deactivated' );

        return static::success( $input, [
            'user_id'   => $user_id,
            'activated' => true,
        ] );
    }
}
