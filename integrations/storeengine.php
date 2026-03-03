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
            'product_purchased' => [
                'label' => 'Product Purchased', 
                'hook'  => 'storeengine/checkout/after_place_order'
            ],
            'order_status_update' => [
                'label' => 'Order Status Updated',
                'hook'  => 'storeengine/order/status_changed'
            ],
            'order_status_on_hold' => [
                'label' => 'Order Status Set To On Hold',
                'hook'  => 'storeengine/order_status_on_hold'
            ],
            'order_status_pending_payment' => [
                'label' => 'Order Status Set To Pending Payment', 
                'hook'  => 'storeengine/order_status_pending_payment'
            ],
            'order_status_processing' => [
                'label' => 'Order Status Set To Processing',      
                'hook'  => 'storeengine/order_status_processing'
            ],
            'order_status_completed' => [
                'label' => 'Order Status Set To Completed',       
                'hook'  => 'storeengine/order_status_completed'
            ],
            'order_status_cancelled' => [
                'label' => 'Order Status Set To Cancelled',       
                'hook'  => 'storeengine/order_status_cancelled'
            ],
            'order_status_draft' => [
                'label' => 'Order Status Set To Draft',           
                'hook'  => 'storeengine/order_status_auto-draft'
            ],
            'order_status_trash' => [
                'label' => 'Order Status Set To Trash',           
                'hook'  => 'storeengine/order_status_trash'
            ],
            'payment_complete' => [
                'label' => 'Completed Payment',       
                'hook'  => 'storeengine/payment_complete'
            ],
            'payment_processing' => [
                'label' => 'Processing Payment',       
                'hook'  => 'storeengine/payment_processing'
            ],
            'payment_pending' => [
                'label' => 'Pending Payment',       
                'hook'  => 'storeengine/payment_pending'
            ],
            'payment_confirmed' => [
                'label' => 'Pending Payment',       
                'hook'  => 'storeengine/payment_confirmed'
            ],
            'customer_created' => [
                'label' => 'Customer Created',        
                'hook'  => 'storeengine/checkout/customer_created'
            ],
            'update_customer' => [
                'label' => 'Customer Updated',        
                'hook'  => 'storeengine/stripe/update_customer'
            ],
            'order_customer_note_added' => [
                'label' => 'Customer Note Added to Order',        
                'hook'  => 'storeengine/order/new_customer_note'
            ],
            'order_customer_note_deleted' => [
                'label' => 'Customer Note Deleted From Order',    
                'hook'  => 'storeengine/order/note_deleted'
            ],
        ]; 
    }

    private static function resolve_order_payload( $order , array $extra= [] ) {
        if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
            $order_id = (int) $order->get_id();
        }
        elseif ( is_int( $order ) ) {
            $order_id = $order;
        }
        else {
            return false;
        }

        if ( ! $order_id ) return false;

        $order = storeengine_get_order( $order_id );

        if ( ! $order) return false;

        return array_merge([
            'order_id'       => $order_id,
            'order_number'   => $order->get_order_number(),
            'order_status'   => $order->get_status(),
            'total'          => $order->get_total(),
            'currency'       => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'customer_email' => method_exists( 
                $order, 'get_billing_email' ) ? 
                $order->get_billing_email() : '',
            'customer_name'  => method_exists( 
                $order, 'get_billing_first_name' ) ? 
                trim(
                    $order->get_billing_first_name() . ' ' .
                    $order->get_billing_last_name()
                ) : '',

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
                $order_id        = $args[0] ?? 0;
                $old_status      = $args[1] ?? '';
                $restored_status = $args[2] ?? '';

                if ( ! $order_id ||  $old_status !== 'trash') return false;

                return self::resolve_order_payload( $order_id, [
                    'old_status'        => $old_status,
                    'restored_status'   => $restored_status,
                ]);

            case 'payment_refunded':
                $order_id        = $args[0] ?? 0;
                $refunded_amount = $args[1] ?? 0;
                $refunded_reason = $args[2] ?? '';

                if ( ! $order_id ) return false;

                return self::resolve_order_payload( $order_id, [
                    'refunded_amount' => $refunded_amount,
                    'refunded_reason' => $refunded_reason,
                ]);

            case 'order_customer_note_added':
                $note  = $args[0] ?? '';
                $order = $args[1] ?? null;

                if ( ! $order || ! is_object( $order ) ) return false;

                return self::resolve_order_payload( $order, [
                    'note' => $note,
                ]);


            case 'order_customer_note_deleted':
                $note_id  = $args[0] ?? 0;
                $note_obj = $args[1] ?? null;

                if ( ! $note_obj || ! is_object( $note_obj ) ) return false;

                $order_id = $note_obj->order_id ?? 0;

                if ( ! $order_id ) return false;

                return self::resolve_order_payload( $order_id, [
                    'deleted_note_id' => $note_id,
                    'deleted_note'    => $note_obj->content ?? '',
                ]);
        }
        return false;
    }
}
