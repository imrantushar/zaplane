<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Diviformbuilder extends IntegrationBase {

    public static function get_slug(): string {
        return 'diviformbuilder';
    }

    public static function get_triggers(): array {
        return [
       
            'df_after_process' => ['label' => 'Form Submission', 'hook' => 'df_after_process'],
        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'df_after_process':
                 ray($args);
                $form_data = $args[0] ?? [];
                    ray($form_data);

                return [
                    'form_id' => $form_data['form_id'] ?? '',
                    'post_id' => $form_data['post_id'] ?? 0,
                    'email' => $form_data['email'] ?? '',
                    'name' => $form_data['name'] ?? '',
                    'message' => $form_data['message'] ?? '',
                    'submitted_at' => current_time('mysql'),
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}