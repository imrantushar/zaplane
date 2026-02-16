<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Divi extends IntegrationBase {

    public static function get_slug(): string {
        return 'divi';
    }

    public static function get_triggers(): array {
        return [
       
            'divi_contact_form_submitted' => ['label' => 'Divi Contact Form Submitted', 'hook' => 'et_pb_contact_form_submit'],
        ];
    }

    public static function get_actions(): array {
        return [];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'divi_contact_form_submitted':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];

                return [
                    'form_id' => $form_meta['contact_form_id'] ?? '',
                    'post_id' => $form_meta['post_id'] ?? 0,
                    'email' => $form_fields['email']['value'] ?? '',
                    'name' => $form_fields['name']['value'] ?? '',
                    'message' => $form_fields['message']['value'] ?? '',
                    'submitted_at' => current_time('mysql'),
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}