<?php

namespace Zaplane\Integrations\Wordpress\Actions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Zaplane\Framework\Classes\BaseAction;

class SchedulePost extends BaseAction {

    public static function get_label(): string {
        return 'Schedule Post';
    }

    public static function get_config_schema(): array {
        return [
            [
                'key'      => 'post_id',
                'label'    => 'ID',
                'type'     => 'expression',
                'required' => true,
            ],
            [
                'key'      => 'schedule_date',
                'label'    => 'Schedule Date & Time',
                'type'     => 'datetime',
                'required' => true,
            ],
            [
                'key'     => 'post_status',
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    [ 'label' => 'Future', 'value' => 'future' ],
                    [ 'label' => 'Draft',  'value' => 'draft' ],
                ],
                'default' => 'future',
            ],
        ];
    }

    public static function get_output_schema(): array {
        return [
            'post_id' => 'integer',
        ];
    }

    public static function execute( array $config, array $input, array $credentials = [] ): array {
        $post_id       = $config['post_id'] ?? 0;
        $schedule_date = $config['schedule_date'] ?? '';
        $status        = $config['post_status'] ?? 'future';

        $result = wp_update_post( [
            'ID'            => $post_id,
            'post_status'   => $status,
            'post_date'     => $schedule_date,
            'post_date_gmt' => get_gmt_from_date( $schedule_date ),
        ], true );

        if ( is_wp_error( $result ) ) {
            throw new \Exception( $result->get_error_message() );
        }

        return static::success( $input, [ 'post_id' => $post_id ] );
    }
}
