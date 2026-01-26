<?php
namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;
use WPCF7_ContactForm;
use WPCF7_Submission;

class ContactForm extends IntegrationBase {

    public static function get_slug(): string {
        return 'Contact Form 7';
    }

    public static function get_triggers(): array {
        return [
            'form_submitted' => [
                'label' => 'Form Submitted', 
                'hook'  => 'wpcf7_before_send_mail'
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

    public static function handle_form_submitted($contact_form, &$abort = null, $submission_obj = null) {
        if (!class_exists('WPCF7_Submission') || !$contact_form) return false;

        $submission = WPCF7_Submission::get_instance();
        if (!$submission) return false;

        $form_id = $contact_form->id();
        $form_data = $submission->get_posted_data();
        $files = $submission->uploaded_files();
        $form_data = array_merge($form_data, self::setFileRoot($files));

        $post_id = (int) $submission->get_meta('container_post_id');
        if ($post_id !== 0) $form_data['post_id'] = $post_id;

        return [
        'form_id' => $form_id,
        'form_data' => $form_data,
        ];
    }

    public static function setFileRoot( $files ) {
        $all_files = [];
        foreach ( $files as $key => $file ) {
            $all_files[ $key ] = is_array( $file ) ? self::setFileRoot( $file ) : self::fileUrl( $file );
        }
        return $all_files;
    }

    public static function fileUrl( $file ) {
        $upload_dir = wp_upload_dir();
        $file_base_url = $upload_dir['baseurl'];
        $file_base_path = $upload_dir['basedir'];
        if ( is_array( $file ) ) {
            $url = [];
            foreach ( $file as $file_index => $file_url ) {
                $url[ $file_index ] = str_replace( $file_base_path, $file_base_url, $file_url );
            }
        } else {
            $url = str_replace( $file_base_path, $file_base_url, $file );
        }
        return $url;
    }
    
    public static function resolve_trigger( array $node, array $args ) {

        switch ( $node['event'] ) {

            case 'form_submitted':
                $payload = self::handle_form_submitted($args[0] ?? null, $args[1] ?? null, $args[2] ?? null);
                if (!$payload) return false;

                $flows = get_option('wp_contact_form_flows', []);


                foreach ($flows as $flow) {
                    if (empty($flow['nodes'])) continue;


                    $trigger_node = $flow['nodes'][0] ?? null;
                    if (!$trigger_node) continue;


                    $config_form_id = $trigger_node['config']['form_id'] ?? 'any';
                    if ($config_form_id !== 'any' && $config_form_id != $payload['form_id']) continue;


                    if (is_callable($trigger_node['callback'] ?? null)) {
                    $trigger_node['callback']($payload);
                    }
                }
                return $payload;
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
