<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;
use WPCF7_ContactForm;
use WPCF7_Submission;

class ContactForm extends IntegrationBase {

    public static function get_slug(): string {
        return 'contact Form 7';
    }

    public static function get_triggers(): array {
        return [
            'form_submitted' => [
                'label' => 'Form Submitted ( Before Mail )', 
                'hook'  => 'wpcf7_before_send_mail'
            ],
            'form_mail_sent' => [
                'label' => 'Form Mail Sent', 
                'hook'  => 'wpcf7_mail_sent'
            ],
            'form_mail_failed' => [
                'label' => 'Form Mail Failed', 
                'hook'  => 'wpcf7_mail_failed'
            ],
            'form_submit' => [
                'label' => 'Form Submit', 
                'hook'  => 'wpcf7_submit'
            ],
            'form_created' => [
                'label' => 'Form Created', 
                'hook'  => 'wpcf7_after_create'
            ],
            'form_updated' => [
                'label' => 'Form Updated', 
                'hook'  => 'wpcf7_after_update'
            ],
            'form_save' => [
                'label' => 'Form Save', 
                'hook'  => 'wpcf7_after_save'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {

        if ($trigger === 'form_submitted') {
            $options = [
                ['label'=>'Any From','value'=>'any'],
            ];
            if ( class_exists( 'WPCF7_ContactForm' ) ) {
                $forms = \WPCF7_ContactForm::find();
                foreach ( $forms as $form ) {
                    $options[]  = [
                        'label' => $form->title(),
                        'value' => $form->id(),
                    ];
                }
            }
            return [
                [
                    'key'      => 'form_id',
                    'label'    => 'Forms',
                    'type'     => 'select',
                    'options'  => $options,
                    'required' => true,
                ],
            ];
        }
        return [];
    }

    public static function resolve_form_submit_payload( $contact_form, &$abort = null, $submission_obj = null ) {
        if ( ! class_exists('WPCF7_Submission') || ! $contact_form ) return false;

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) return false;

        $form_id   = $contact_form->id();
        $form_data = $submission->get_posted_data();
        $files     = $submission->uploaded_files();
        $form_data = array_merge( 
            $form_data, 
            self::resolve_file_root_payload( $files ), 
        );

        $post_id = $submission->get_meta('container_post_id');
        if ( $post_id !== 0 ) $form_data['post_id'] = $post_id;

        return [
            'form_id'   => $form_id,
            'form_data' => $form_data,
        ];
    }

    public static function resolve_file_root_payload( $files ) {
        $all_files = [];
        foreach ( $files as $key => $file ) {
            $all_files[ $key ] = is_array( $file ) ? 
            self::resolve_file_root_payload( $file ) : 
            self::resolve_file_url_payload( $file );
        }
        return $all_files;
    }

    public static function resolve_file_url_payload( $file ) {
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        $base_path  = $upload_dir['basedir'];

        if ( is_array( $file ) ) {
            $url = [];
            foreach ( $file as $key => $file_url ) {
                $url[ $key ] = str_replace( $base_path, $base_url, $file_url );
            }
        } else {
            $url = str_replace( $base_path, $base_url, $file );
        }
        return $url;
    }

    protected static function resolve_helper_payload( $payload ) {
        $flows = get_option('wp_contact_form_flows', []);

        foreach ( $flows as $flow ) {
            if ( empty( $flow['nodes'] ) ) continue;

            $trigger_node = $flow['nodes'][0] ?? null;
            if ( ! $trigger_node ) continue;

            $config_form_id = $trigger_node['config']['form_id'] ?? 'any';
            if ( $config_form_id !== 'any' && $config_form_id != $payload['form_id'] ) continue;

            if ( is_callable( $trigger_node['callback'] ?? null ) ) {
                $trigger_node['callback']( $payload );
            }
        }
    }

    public static function resolve_form_admin_payload( $contact_form ) {
        if ( ! $contact_form || ! method_exists( $contact_form, 'id' ) ) return false;
        return [
            'form_id'    => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'status'     => $contact_form->prop('status'),
            'locale'     => $contact_form->locale(),
            'title'      => current_time('mysql'),
            'user_id'    => get_current_user_id(),
        ];
    }
    
    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'form_submitted':
            case 'form_mail_sent':
            case 'form_mail_failed':
            case 'form_submit':
                $payload = self::resolve_form_submit_payload( 
                    $args[0] ?? null, 
                    $args[1] ?? null, 
                    $args[2] ?? null
                );
                if ( ! $payload ) return false;
                $event_map = [
                    'form_mail_sent'   => ['mail_status', 'sent'],
                    'form_mail_failed' => ['mail_status', 'failed'],
                    'form_submit'      => ['submit_status', 'submitted'],
                ];
                if ( isset( $event_map[ $node['event'] ] ) ) {
                    [ $key, $value ] = $event_map[ $node['event'] ];
                    $payload[ $key ] = $value;
                }

                self::resolve_helper_payload($payload);
                return $payload;

            case 'form_created':
            case 'form_updated':
            case 'form_save':
                $payload = self::resolve_form_admin_payload( 
                    $args[0] ?? null, 
                );
                if ( ! $payload ) return false;
                $action_map = [
                    'form_created' => 'created',
                    'form_updated' => 'updated',
                    'form_save'    => 'saved',
                ];
                if ( isset( $action_map[ $node['event'] ] ) ) {
                    $payload['action'] = $action_map[ $node['event'] ];
                }

                self::resolve_helper_payload($payload);
                return $payload;
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
