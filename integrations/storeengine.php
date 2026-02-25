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
            'product_purchased'            => ['label' => 'Product Purchased',                   'hook' => 'storeengine/checkout/after_place_order'],
            'created_product'            => ['label' => 'Created Product',                   'hook' => 'storeengine/product/created'],
            'updated_product'            => ['label' => 'Updated Product',                   'hook' => 'storeengine/product/updated'],
            'order_status_update'          => ['label' => 'Order Status Updated',                'hook' => 'storeengine/order/status_changed'],
            'order_status_on_hold'         => ['label' => 'Order Status Set To On Hold',         'hook' => 'storeengine/order_status_on_hold'],
            'order_status_pending_payment' => ['label' => 'Order Status Set To Pending Payment', 'hook' => 'storeengine/order_status_pending_payment'],
            'order_status_processing'      => ['label' => 'Order Status Set To Processing',      'hook' => 'storeengine/order_status_processing'],
            'order_status_completed'       => ['label' => 'Order Status Set To Completed',       'hook' => 'storeengine/order_status_completed'],
            'order_status_cancelled'       => ['label' => 'Order Status Set To Cancelled',       'hook' => 'storeengine/order_status_cancelled'],
            'order_status_draft'           => ['label' => 'Order Status Set To Draft',           'hook' => 'storeengine/order_status_auto-draft'],
            'order_status_trash'           => ['label' => 'Order Status Set To Trash',           'hook' => 'storeengine/order_status_trash'],
            'order_customer_note_added'    => ['label' => 'Customer Note Added to Order',        'hook' => 'storeengine/order/new_customer_note'],
            'order_customer_note_deleted'  => ['label' => 'Customer Note Deleted From Order',    'hook' => 'storeengine/order/note_deleted'],
            'order_restored'               => ['label' => 'Order Restored',                      'hook' => 'storeengine/order/status_changed'],
            // 'payment_initiated'            => ['label' => 'Payment Initiated',                   'hook' => 'payment_initiated'],
            // 'payment_successful'           => ['label' => 'Payment Successful',                  'hook' => 'payment_successful'],
            // 'payment_failed'               => ['label' => 'Payment Failed',                      'hook' => 'payment_failed'],
            // 'payment_refunded'             => ['label' => 'Payment Refunded',                    'hook' => 'payment_refunded'],
            // 'customer_created'             => ['label' => 'Customer Created',                    'hook' => 'customer_created'],
            // 'customer_updated'             => ['label' => 'Customer Updated',                    'hook' => 'customer_updated'],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( $trigger === 'updated_product' ) {
            return [
                [
                    'key'      => 'product_id',
                    'label'    => 'ID',
                    'type'     => 'expression',
                    'required' => true,
                ],
            ];
        }
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

            case 'created_product':
                $product_id = $args[0] ?? 0;
                if ( ! $product_id ) return false;
                $product = storeengine_get_product( $product_id );
                return [
                    'product_id'   => $product_id,
                    'product_name' => $product->get_name(),
                    'price'        => $product->get_price(),
                    'description'  => $product->get_description(),
                    'status'       => $product->get_status(),
                ];

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

            case 'payment_initiated':
                $order_id = $args[0] ?? 0;
                $payment_method = $args[1] ?? '';
                if ( ! $order_id ) return false;
                return self::resolve_order_payload( $order_id, [
                    'payment_method'   => $payment_method,
                ]);
              
            case 'payment_successful':
            case 'payment_failed':
                $order_id = $args[0] ?? 0;
                $payment_method = $args[1] ?? '';
                $transaction_id = $args[2] ?? '';
                if ( ! $order_id ) return false;
                return self::resolve_order_payload( $order_id, [
                    'payment_method' => $payment_method,
                    'transaction_id' => $transaction_id,
                ]);
        
            case 'payment_refunded':
                $order_id = $args[0] ?? 0;
                $refunded_amount = $args[1] ?? 0;
                $refunded_reason = $args[2] ?? '';
                if ( ! $order_id ) return false;
                return self::resolve_order_payload( $order_id, [
                    'refunded_amount' => $refunded_amount,
                    'refunded_reason' => $refunded_reason,
                ]);

            case 'customer_created':
                $customer_id = $args[0] ?? 0;
                $customer_name = $args[1] ?? '';
                $customer_email = $args[2] ?? '';
                if ( ! $customer_id ) return false;
                return [
                    'customer_id'    => $customer_id,
                    'customer_name'  => $customer_name,
                    'customer_email' => $customer_email,
                ];

            case 'customer_updated':
                $customer_id = $args[0] ?? 0;
                $change = $args[1] ?? [];
                if ( ! $customer_id ) return false;
                return [
                    'customer_id'    => $customer_id,
                    'change'  => $change,
                ];

        }
        return false;
    }

    public static function get_actions(): array {
        return [
            'create_product'   => ['label'=>'Create Product'],
            'update_product'   => ['label'=>'Update Product'],
            'create_order'   => ['label'=>'Create Order'],
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [
            'create_product' => [
                ['key'=>'product_name','label'=>'Product Name','type'=>'text','required'=>true],
                ['key'=>'price','label'=>'Price','type'=>'number','required'=>true],
                ['key'=>'description','label'=>'Description','type'=>'textarea',],
                ['key'=>'status','label'=>'Status','type'=>'select','options'=>[
                    ['label'=>'Draft','value'=>'draft'],
                    ['label'=>'Publish','value'=>'publish'],
                ]],
            ],
        ];

        return $schemas[$action] ?? [];
    }

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];

        switch ( $node['data']['event'] ?? '' ) {

            case 'create_product':

                $name        = $config['product_name'] ?? '';
                $price       = $config['price'] ?? '';
                $description = $config['description'] ?? '';
                $status      = $config['status'] ?? 'publish';

                if ( ! $name || $price === '' ) {
                    return ['success' => false, 'message' => 'Product name and price required'];
                }

                $product_id = storeengine_create_product([
                    'name'        => $name,
                    'price'       => $price,
                    'description' => $description,
                    'status'      => $status,
                ]);

                if ( ! $product_id ) {
                    return ['success' => false];
                }

                return [
                    'success'    => true,
                    'product_id' => $product_id,
                    'product_name' =>$name,
                    'price'      => $price,
                    'status'     => $status,
                ];

        }
        return ['port'=>'main','data'=>$input];
    }
}
