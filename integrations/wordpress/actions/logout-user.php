<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class LogoutUser extends BaseAction {

    public static function get_label(): string {
        return 'Logout User';
    }

    public static function get_config_schema(): array {
        return [];
    }

    public static function get_output_schema(): array {
        return [
            'logged_out' => 'boolean',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        wp_logout();

        return static::success( $input, [
            'logged_out' => true,
        ] );
    }
}
