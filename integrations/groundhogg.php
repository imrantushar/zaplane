<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;
use Groundhogg\Tag;

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
                        'value' => $tag->tag_id,
                        'label' => $tag->tag_name,
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
        $owner_data = null;
        $owner_id   = method_exists( $contact, 'get_owner_id' ) ? $contact->get_owner_id() : 0;
        if ( $owner_id ) {
            $user = get_userdata( $owner_id );
            if ( $user ) {
                $owner_data = [
                    'id'        => $user->ID,
                    'name'      => $user->display_name,
                    'email'     => $user->user_email,
                ];
            }
        }
        return [
            'id'            => $contact->get_id(),
            'first_name'    => (string) $contact->get_first_name(),
            'last_name'     => (string) $contact->get_last_name(),
            'email'         => (string) $contact->get_email(),
            'optin_status'  => $contact->get_optin_status(),
            'date_created'  => $contact->get_date_created(),
            'owner'         => $owner_data,
        ];
    }

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'created_contact':
                $contact = $args[2] ?? null;
                if ( ! $contact ) return false;
                return [
                    'success' => true,
                    'contact' => self::resolve_contact_payload( $contact ),
                ];

            case 'added_tag':
            case 'removed_tag':
                
                $contact = $args[0] ?? null;
                $tag_id  = $args[1] ?? null;

                if ( ! $contact instanceof \Groundhogg\Contact ) return false;
                if ( ! is_numeric( $tag_id ) ) return false;

                $tag_id     = (int) $tag_id;

                $select_tag = $node['config']['tag_id'] ?? 'any';
                if ( $select_tag !== 'any' && (int) $select_tag !== (int) $tag_id ) {
                    return false;
                }

                $tag_data = null;
                $tag      = new Tag( $tag_id );

                if ( $tag && $tag->exists() ) {
                    $tag_data = [
                        'id'   => $tag->get_id(),
                        'name' => $tag->get_name(),
                        'slug' => $tag->get_slug(),
                    ];
                }
                return [
                    'success'   => true,
                    'contact'   => self::resolve_contact_payload( $contact ),
                    'object_id' => $tag_id,
                    'tag'       => $tag_data,
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
