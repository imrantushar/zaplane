<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class ContactForm extends IntegrationBase {

    public static function get_slug(): string {
        return 'Contact From 7';
    }

    public static function get_triggers(): array {
        return [
            'from_submitted' => [
                'label' => 'From Submitted', 
                'hook'  => 'wpcf7_before_send_mail'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        return [];
    }
    
    // private static function resolve_order_payload( int $order_id , array $extra= [] ) {
    //     $order = storeengine_get_order( $order_id );
    //             if ( ! $order) return false;
    //             return array_merge([
    //                 'order_id'       => $order_id,
    //                 'order_number'   => $order->get_order_number(),
    //                 'order_status'   => $order->get_status(),
    //                 'total'          => $order->get_total(),
    //                 'currency'       => $order->get_currency(),
    //                 'payment_method' => $order->get_payment_method(),
    //                 'customer_email' => $order->get_customer_email(),
    //                 'customer_name'  => $order->get_customer_name(),
    //                 'items'          => $order->get_items(),
    //             ], $extra);
    // }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'from_submitted':
                $order_id = $args[0] ?? 0;
                if ( ! $order_id ) return false;
                //return self::resolve_order_payload( $order_id);

            
        }
        return false;
    }

    public static function get_actions(): array {
        return [
            //example
           'create_product'   => ['label'=>'Create Product'],
        ];
    }

    public static function get_action_config_schema( string $action ): array {

        $schemas = [

        //example
            // 'create_product' => [
            //     ['key'=>'product_name','label'=>'Product Name','type'=>'text','required'=>true],
            //     ['key'=>'price','label'=>'Price','type'=>'number','required'=>true],
            //     ['key'=>'description','label'=>'Description','type'=>'textarea',],
            //     ['key'=>'status','label'=>'Status','type'=>'select','options'=>[
            //         ['label'=>'Draft','value'=>'draft'],
            //         ['label'=>'Publish','value'=>'publish'],
            //     ]],
            //],
        ];

        return $schemas[$action] ?? [];
    }

    public static function execute_node( array $node, array $input ): array {

        $config = $node['data']['config'] ?? [];

        switch ( $node['data']['event'] ?? '' ) {

        //example
            // case 'create_product':

            //     $name        = $config['product_name'] ?? '';
            //     $price       = $config['price'] ?? '';
            //     $description = $config['description'] ?? '';
            //     $status      = $config['status'] ?? 'publish';

            //     if ( ! $name || $price === '' ) {
            //         return ['success' => false, 'message' => 'Product name and price required'];
            //     }

            //     $product_id = storeengine_create_product([
            //         'name'        => $name,
            //         'price'       => $price,
            //         'description' => $description,
            //         'status'      => $status,
            //     ]);

            //     if ( ! $product_id ) {
            //         return ['success' => false];
            //     }

            //     return [
            //         'success'    => true,
            //         'product_id' => $product_id,
            //         'product_name' =>$name,
            //         'price'      => $price,
            //         'status'     => $status,
            //     ];

        }
        return ['port'=>'main','data'=>$input];
    }
}
