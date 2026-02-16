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
            'form_success' => [
                'label' => 'UAGB Form Success',
                'hook'  => 'uagb_form_success'
            ]
        ];
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        return [];
    }

    public static function resolve_trigger( array $node, array $args ) {
        $form_data = $_POST['form_data'] ?? '';
        $form_id   = $_POST['id'] ?? 0;
        
        if ( empty( $form_data ) ) {
            return false;
        }
        
        return [
            'form_data'    => json_decode($form_data, true),
            'form_id'      => $form_id,
            'submitted_at' => current_time('mysql'),
        ];
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