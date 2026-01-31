<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class DeleteUser extends BaseAction {

    public static function get_label(): string {
        return 'Delete User';
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
                'key'   => 'reassign',
                'label' => 'Reassign User ID',
                'type'  => 'expression',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user_id' => 'integer',
            'deleted' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        require_once ABSPATH . 'wp-admin/includes/user.php';

        $reassign = ! empty( $config['reassign'] ) ? (int) $config['reassign'] : null;
        $result   = wp_delete_user( $config['user_id'], $reassign );

        if ( ! $result ) {
            throw new \Exception( 'Failed to delete user' );
        }

        return static::success( $input, [
            'user_id' => $config['user_id'],
            'deleted' => true,
        ] );
    }
}
