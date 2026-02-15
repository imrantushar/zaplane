<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Beaverbuilder extends IntegrationBase {

    public static function get_slug(): string {
        return 'beaverbuilder';
    }

    public static function get_triggers(): array {
        return [
            'contact_form_submission' => ['label' => 'Contact Form Submission', 'hook' => 'fl_builder_contact_form_submission'],
            'login_form_submission' => ['label' => 'Login Form Submission', 'hook' => 'fl_builder_login_form_submission'],
            'subscribe_form_submission' => ['label' => 'Subscribe Form Submission', 'hook' => 'fl_builder_subscribe_form_submission'],
        ];
    }

    public static function get_actions(): array {
        return [];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'contact_form_submission':
                 ray($args);
                $form_data = $args[0] ?? [];
                if (empty($form_data)) return false;

                return [
                    'name' => $form_data['name'] ?? '',
                    'email' => $form_data['email'] ?? '',
                    'message' => $form_data['message'] ?? '',
                ];

            case 'login_form_submission':
                $form_data = $args[0] ?? [];
                ray($args);
                if (empty($form_data)) return false;

                return [
                    'username' => $form_data['username'] ?? '',
                    'user_id' => $form_data['user_id'] ?? 0,
                ];

            case 'subscribe_form_submission':
                $form_data = $args[0] ?? [];
                ray($args);
                if (empty($form_data)) return false;

                return [
                    'email' => $form_data['email'] ?? '',
                    'name' => $form_data['name'] ?? '',
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}