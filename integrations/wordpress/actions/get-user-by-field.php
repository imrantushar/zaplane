<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;
use Zaplane\Integrations\Wordpress\WordpressPayloadHelpers;

class GetUserByField extends BaseAction {

    public static function get_label(): string {
        return 'Get User by Field';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'field',
                'label'    => 'Field',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    [ 'label' => 'ID', 'value' => 'id' ],
                    [ 'label' => 'Slug', 'value' => 'slug' ],
                    [ 'label' => 'Email', 'value' => 'email' ],
                    [ 'label' => 'Login', 'value' => 'login' ],
                ],
            ],
            [
                'key'      => 'value',
                'label'    => 'Value',
                'type'     => 'expression',
                'required' => true,
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'user' => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $field = $config['field'] ?? 'id';
        $value = $config['value'] ?? '';

        $user = get_user_by( $field, $value );

        if ( ! $user ) {
            return static::success( $input, [ 'user' => null ] );
        }

        return static::success( $input, [
            'user' => WordpressPayloadHelpers::resolve_user( $user->ID ),
        ] );
    }
}
