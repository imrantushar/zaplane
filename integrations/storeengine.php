<?php
namespace Zaplane\Integrations;



if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;


class Storeengine extends IntegrationBase {
    public static function get_slug(): string {
        return 'storeengine';
    }

    public static function get_triggers(): array {
        return [
            'product_purchased' => ['label' => 'Product Purchased', 'hook' => 'product_purchased'],
            'order_status_update' => ['label' => 'Order Status Updated', 'hook' => 'order_status_update'],
            'order_status_on_hold' => ['label' => 'Order Status Set To On Hold', 'hook' => 'order_status_on_hold'],
            'order_status_pending_payment' => ['label' => 'Order Status Set To Pending Payment', 'hook' => 'order_status_pending_payment'],
            'order_status_processing' => ['label' => 'Order Status Set To Processing', 'hook' => 'order_status_processing'],
            'order_status_completed' => ['label' => 'Order Status Set To Completed', 'hook' => 'order_status_completed'],
            'order_status_cancelled' => ['label' => 'Order Status Set To Cancelled', 'hook' => 'order_status_cancelled'],
            'order_status_draft' => ['label' => 'Order Status Set To Draft', 'hook' => 'order_status_draft'],
            'order_status_trash' => ['label' => 'Order Status Set To Trash', 'hook' => 'order_status_trash'],
            'order_customer_note_added' => ['label' => 'Customer Note Added to Order', 'hook' => 'order_customer_note_added'],
            'order_customer_note_deleted' => ['label' => 'Customer Note Deleted From Order', 'hook' => 'order_customer_note_deleted'],
            'order_restored' => ['label' => 'Order Restored', 'hook' => 'order_restored'],
        ];
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        return [];
    }
    
    private static function resolve_order_payload( int $order_id , array $extra= [] ) {
        $order = storeengine_get_order( $order_id );
                if ( ! $order) return false;
                return array_merge([
                    'order_id'       => $order_id,
                    'order_number'   => $order->get_order_number(),
                    'order_status'   => $order->get_status(),
                    'total'          => $order->get_total(),
                    'currency'       => $order->get_currency(),
                    'payment_method' => $order->get_payment_method(),
                    'customer_email' => $order->get_customer_email(),
                    'customer_name'  => $order->get_customer_name(),
                    'items'          => $order->get_items(),
                ], $extra);
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'product_purchased':
                $order_id = $args[0] ?? 0;
                if ( ! $order_id ) return false;
                return self::resolve_order_payload( $order_id);

            case 'order_status_update':
            case 'order_status_on_hold':
            case 'order_status_pending_payment':
            case 'order_status_processing':
            case 'order_status_completed':
            case 'order_status_cancelled':
            case 'order_status_draft':
            case 'order_status_trash':
                $order_id   = $args[0] ?? 0;
                $old_status = $args[1] ?? '';
                $new_status = $args[2] ?? '';
                if ( ! $order_id || ! $new_status ) return false;
                return self::resolve_order_payload( $order_id, [
                    'old_status' => $old_status, 
                    'new_status' => $new_status,
                ]);

            case 'order_restored':
                $order_id   = $args[0] ?? 0;
                $old_status = $args[1] ?? '';
                $restored_status = $args[2] ?? '';
                if ( ! $order_id ||  $old_status !== 'trash') return false;
                return self::resolve_order_payload( $order_id, [
                    'old_status'        => $old_status,
                    'restored_status'   => $restored_status,
                ]);

            case 'order_customer_note_added':
                $order_id   = $args[0] ?? 0;
                $note       = $args[1] ?? '';
                $customer   = $args[2] ?? '';
                if ( ! $order_id || ! $note ) return false;
                return self::resolve_order_payload( $order_id, [
                    'note'           => $note,
                    'note_author'    => $customer,
                ]);

            case 'order_customer_note_deleted':
                $order_id   = $args[0] ?? 0;
                $note       = $args[1] ?? '';
                $admin   = $args[2] ?? '';
                if (! $order_id || ! $note ) return false;
                return self::resolve_order_payload( $order_id, [
                    'deleted_note'   => $note,
                    'deleted_by'     => $admin,
                ]);
        }
        return false;
    }

    public static function get_actions(): array {
        return [
            'create_post'                   => ['label'=>'Create Post'], // example code
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [
            'action' => ['schema_key' => 'schema_value' ] // example code
        ];

        return $schemas[$action] ?? [];
    }


    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];
        $event = $node['data']['event'];

        $method = 'action_' . $event;

        if (method_exists(static::class, $method)) {
            return static::$method($config, $input);
        }

        return ['port' => 'main', 'data' => $input];
    }
}
