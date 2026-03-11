<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Storeengine extends IntegrationBase {

    public static function get_slug(): string {
        return 'storeengine';
    }

	public static function get_name(): string {
		return 'StoreEngine';
	}

	public static function get_icon(): string {
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
            'order_restored' => [
                'label' => 'Order Restored',                      
                'hook'  => 'storeengine/order/status_changed'
            ],
            'payment_refunded' => [
                'label' => 'Payment Refunded',
                'hook'  => 'storeengine/subscription/payment_refunded'
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

    private static function resolve_order_payload($order, array $extra = []) {
        if (is_object($order) && method_exists($order, 'get_id')) {
            $order_id = (int) $order->get_id();
        } elseif (is_int($order)) {
            $order_id = $order;
        } else {
            return false;
        }

        if (!$order_id) {
            return false;
        }

        $order_obj = storeengine_get_order($order_id);

        if (!$order_obj) {
            return false;
        }

        $items = array_map(function ($item) {

            if (is_array($item)) {
                return [
                    'product_id' => $item['product_id'] ?? '',
                    'name'       => $item['name'] ?? '',
                    'quantity'   => $item['quantity'] ?? '',
                    'total'      => $item['total'] ?? '',
                ];
            }

            if (is_object($item)) {
                return [
                    'product_id' => method_exists($item, 'get_product_id') ? $item->get_product_id() : '',
                    'name'       => method_exists($item, 'get_name') ? $item->get_name() : '',
                    'quantity'   => method_exists($item, 'get_quantity') ? $item->get_quantity() : '',
                    'total'      => method_exists($item, 'get_total') ? $item->get_total() : '',
                ];
            }

            return [];

        }, array_values($order_obj->get_items()));

        return array_merge([
            'order_id'       => $order_id,
            'order_number'   => $order_obj->get_order_number(),
            'order_status'   => $order_obj->get_status(),
            'total'          => $order_obj->get_total(),
            'currency'       => $order_obj->get_currency(),
            'payment_method' => $order_obj->get_payment_method(),
            'customer_email' => method_exists($order_obj, 'get_billing_email')
                ? $order_obj->get_billing_email()
                : '',
            'customer_name'  => method_exists($order_obj, 'get_billing_first_name')
                ? trim(
                    $order_obj->get_billing_first_name() . ' ' .
                    $order_obj->get_billing_last_name()
                )
                : '',
            'items' => $items,
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
                $order   = $args[1] ?? null;
                $transition = $args[2] ?? [];

                if ( ! $order || ! is_array($transition) ) return false;

                $old_status = $transition['from'] ?? '';
                $new_status = $transition['to'] ?? '';

                return self::resolve_order_payload( $order, [
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
