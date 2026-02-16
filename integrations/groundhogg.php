<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Groundhogg extends IntegrationBase {

    public static function get_slug(): string {
        return 'groundhogg';
    }

    public static function get_triggers(): array {
        return [
            'created_contact' => [
                'label' => 'Created Contact', 
                'hook'  => 'groundhogg/contact/post_create'
            ],
            'added_tag' => [
                'label' => 'Tag Added To Contact', 
                'hook'  => 'groundhogg/contact/tag_applied'
            ],
            'removed_tag' => [
                'label' => 'Tag Remove From Contact', 
                'hook'  => 'groundhogg/contact/tag_removed'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        if ( in_array( $trigger, ['added_tag','removed_tag'], true ) ) {
            $options = [ 
                ['label' => 'Any Tag', 'value' => 'any'],
            ];
            if ( function_exists( '\Groundhogg\get_db' ) ) {
                $tags = \Groundhogg\get_db('tags')->query(['limit' => 1000]);
                foreach ( $tags as $tag ) {
                    $options[] = [
                        'value' => $tag->id,
                        'label' => $tag->title,
                    ];
                }
            }
            return [
                [
                    'key'      => 'tag_id',
                    'label'    => 'Tags',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }
        return [];
    }

    private static function resolve_contact_payload( $contact ): array {
        return [
            'first_name'       => $contact->first_name ?? '',
            'last_name'        => $contact->last_name ?? '',
            'email'            => $contact->email ?? '',
            'primary_phone'    => $contact->phone ?? '',
            'phone_ext'        => $contact->phone_ext ?? '',
            'opt_in_status'    => $contact->opt_in_status ?? '',
            'mobile_phone'     => $contact->mobile_phone ?? '',
            'owner'            => $contact->owner->email ?? null,
            'tags'             => $contact->tags ?? [],
            'gdpr_terms'       => $contact->gdpr_terms ?? '',
            'gdpr_data'        => $contact->gdpr_data ?? '',
            'gdpr_marketing'   => $contact->gdpr_marketing ?? '',
        ];
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'created_contact':
                $contact = $args[0] ?? null;

                if ( ! $contact ) return false;

                $contact = $args[2];

                return [
                    'success' => true,
                    'contact' => self::resolve_contact_payload( $contact ),
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
