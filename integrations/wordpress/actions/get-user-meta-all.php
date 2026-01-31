<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class GetUserMetaAll extends BaseAction {

    public static function get_label(): string {
        return 'Get User Metadata (All)';
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
            'user_id' => 'integer',
            'meta'    => 'object',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $user_id = $config['user_id'] ?? 0;
        $meta    = get_user_meta( $user_id );

        // Flatten single-value arrays
        $flattened = [];
        foreach ( $meta as $key => $value ) {
            $flattened[ $key ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
        }

        return static::success( $input, [
            'user_id' => $user_id,
            'meta'    => $flattened,
        ] );
    }
}
