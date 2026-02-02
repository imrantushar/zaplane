<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Spectra extends IntegrationBase {

    public static function get_slug(): string {
        return 'spectra';
    }

    public static function get_triggers(): array {
        return [
            'form_submitted' => [
                'label' => 'Spectra Form Submitted', 
                'hook'  => 'uagb_form_submission'
            ],
            'form_submit' => [
                'label' => 'UAGB Form Submit',
                'hook'  => 'uagb_form_submit'
            ],
            'contact_form_submit' => [
                'label' => 'UAGB Contact Form Submit',
                'hook'  => 'uagb_contact_form_submit'
            ],
            'form_process' => [
                'label' => 'UAGB Form Process',
                'hook'  => 'wp_ajax_uagb_process_forms'
            ],
            'form_process_nopriv' => [
                'label' => 'UAGB Form Process (No Priv)',
                'hook'  => 'wp_ajax_nopriv_uagb_process_forms'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        return [];
    }

    public static function resolve_trigger( array $node, array $args ) {
        error_log('Spectra Hook Fired! Event: ' . ($node['event'] ?? 'unknown'));
        error_log('Spectra Args Count: ' . count($args));
        error_log('Spectra Args: ' . print_r($args, true));
        
        switch ( $node['event'] ) {
            case 'form_submitted':
            case 'form_submit':
            case 'contact_form_submit':
            case 'form_process':
            case 'form_process_nopriv':
                $form_data = $args[0] ?? $_POST ?? [];
                $form_id   = $args[1] ?? ($_POST['form_id'] ?? 0);
                $post_id   = $args[2] ?? ($_POST['post_id'] ?? 0);

                error_log('Form Data: ' . print_r($form_data, true));
                
                if ( empty( $form_data ) ) {
                    return false;
                }
                
                return [
                    'success'      => true,
                    'form_data'    => $form_data,
                    'form_id'      => $form_id,
                    'page_id'      => $post_id,
                    'submitted_at' => current_time('mysql'),
                ];
        }
        
        error_log('Spectra: No matching event case');
        return false;
    }

   public static function get_actions(): array {
        return [];
    }

    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    public static function execute_node( array $node, array $input ): array {
        return ['port' => 'main', 'data' => $input];
    }
}