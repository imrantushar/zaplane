<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class FluentCrm extends IntegrationBase {

    public static function get_slug(): string {
        return 'fluentCRM';
    }

    public static function get_triggers(): array {
        return [
            'tag_added' => [
                'label' => 'Tag Added To Contact',
                'hook' => 'fluentcrm_contact_added_to_tags',
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        if ( $trigger === 'tag_added' ) {
            $options = [ 
                ['label' => 'Any Tag', 'value' => 'any'],
            ];
            if ( class_exists( 'FluentCrm\App\Models\Tag' ) ) {
                $tags = \FluentCrm\App\Models\Tag::all();
                foreach ( $tags as $tag ) {
                    $options[] = [
                        'label' => $tag->title,
                        'value' => $tag->id,
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

    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {
    
            case 'tag_added':
                $contact   = $args[0] ?? null;
                $tag_id = $args[1] ?? [];

                $core_fields = [
                    'id'         => $contact->id ?? $contact->ID ?? 0,
                    'email'      => $contact->email ?? '',
                    'first_name' => $contact->first_name ?? '',
                    'last_name'  => $contact->last_name ?? '',
                    'status'     => $contact->status ?? '',
                    'source'     => $contact->source ?? '',
                    'created_at' => $contact->created_at ?? '',
                    'updated_at' => $contact->updated_at ?? '',
                ];

                $tags = [];
                if (!empty($contact->tags)) {
                    foreach ($contact->tags as $tag) {
                        $tags[] = [
                            'id'   => $tag->id,
                            'name' => $tag->name ?? $tag->title ?? '',
                            'slug' => $tag->slug ?? '',
                        ];
                    }
                }

                return [
                    'success'       => true,
                    'contact'       => $core_fields,
                    'tag_ids_added' => $tag_id,
                    'tags'          => $tags,
                    'contact_obj'   => $contact, 
                ];
                // return [
                // 'success'    => true,
                // 'contact_id' => $contact->ID,
                // 'email'      => $contact->email,
                // 'first_name' => $contact->first_name,
                // 'last_name'  => $contact->last_name,
                // 'tag_id'     => $tag_id,
                // 'contact'    => $contact,
                // ];


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
