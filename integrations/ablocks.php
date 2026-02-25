<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class ABlocks extends IntegrationBase {

    public static function get_slug(): string {
        return 'ablocks';
    }

    public static function get_triggers(): array {
        return [
            'form_before_submit' => [
                'label' => 'Form Before Submit',
                'hook' => 'ablocks/form/before_submit',
            ],
            'form_submitted' => [
                'label' => 'Form Submitted', 
                'hook'  => 'ablocks/form/submitted'
            ],
            'multistep_completed' => [
                'label' => 'Multi-step Form', 
                'hook'  => 'ablocks/form/multistep_completed'
            ],
            'login_form' => [
                'label' => 'Login Form', 
                'hook'  => 'wp_login'
            ],
            'register_form' => [
                'label' => 'Register Form', 
                'hook'  => 'ablocks/form/user_registered'
            ],
            'block_activated' => [
                'label' => 'Block Activated', 
                'hook'  => 'ablocks/block/activated'
            ],
            'block_deactivated' => [
                'label' => 'Block Deactivated', 
                'hook'  => 'ablocks/block/deactivated'
            ],
            'after_save_settings' => [
                'label' => 'Block Deactivated', 
                'hook'  => 'ablocks/after_save_settings'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        return [];
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {
    
            case 'form_before_submit':
                $form_id   = $args['form_id'] ?? 0;
                $form_data = $args['form_data'] ?? [];
                return [
                'success'   => true,
                'form_id'   => $form_id,
                'form_data' => $form_data,
                ];

            case 'form_submitted': 
                $form_id   = $args['form_id'] ?? 0;
                $form_data = $args['form_data'] ?? [];
                $entry_id  = $args['entry_id'] ?? 0;
                return [
                    'success'   => true,
                    'form_id'   => $form_id,
                    'form_data' => $form_data,
                    'entry_id'  => $entry_id,
                ];

            case 'multistep_completed':
                $form_id    = $args['form_id'] ?? 0;
                $step_index = $args['step_index'] ?? null;
                $form_data  = $args['form_data'] ?? [];
                return [
                'success'    => true,
                'form_id'    => $form_id,
                'step_index' => $step_index,
                'form_data'  => $form_data,
                ];

            case 'login_form':
                $user = $args['user'] ?? null;
                $user_name  = $args['user_name'] ?? '';
                $form_id    = $args['form_id'] ?? 0;
                if ( ! $user ) return false;
                return [
                'success'    => true,
                'user_id'    => $user->ID,
                'username'  => $user_name,
                'email'      => $user->user_email,
                'form_id'    => $form_id,
                'login_time' => current_time('mysql'),
                ];

            case 'register_form':
                $user         = $args['user'] ?? null;
                $form_id      = $args['form_id'] ?? 0;
                $fields       = $args['fields'] ?? [];
                $frm_settings = $args['frm_settings'] ?? [];
                if ( ! $user ) return false;
                return [
                'success'     => true,
                'user_id'     => $user->ID,
                'username'    => $user->user_login,
                'email'       => $user->user_email,
                'form_id'     => $form_id,
                'fields'      => $fields,
                'settings'    => $frm_settings,
                'register_at' => current_time('mysql'),
                ];

            case 'block_activated':
            case 'block_deactivated':
                $block_name = $args['block_name'] ?? '';
                $status     = $args['status'] ?? '';
                if ( ! $block_name ) return false;
                return [
                'success'    => true,
                'block_name' => $block_name,
                'status'     => $status,
                ];

            case 'after_save_settings':
                $update  = $args['is_update'] ?? false;
                $type    = $args['type'] ?? 'base';
                $payload = $args['payload'] ?? [];
                return [
                'success'   => true,
                'is_update' => $update,
                'type'      => $type,
                'payload'   => $payload,
                ];


        }
        return false;
    }

    public static function get_actions(): array {
        return [
            //example
           //'create_product'   => ['label'=>'Create Product'],
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
