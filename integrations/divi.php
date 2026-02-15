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
            'divi_theme_activated' => ['label' => 'Divi Theme Activated', 'hook' => 'after_switch_theme'],
            'divi_builder_enabled' => ['label' => 'Divi Builder Enabled', 'hook' => 'et_builder_enabled'],
            'divi_layout_saved' => ['label' => 'Divi Layout Saved', 'hook' => 'et_builder_layout_saved'],
            'divi_module_added' => ['label' => 'Divi Module Added', 'hook' => 'et_builder_module_added'],
            'divi_page_built' => ['label' => 'Page Built with Divi', 'hook' => 'et_builder_page_built'],
            'divi_library_item_saved' => ['label' => 'Divi Library Item Saved', 'hook' => 'et_builder_library_item_saved'],
            'divi_section_added' => ['label' => 'Divi Section Added', 'hook' => 'et_builder_section_added'],
            'divi_row_added' => ['label' => 'Divi Row Added', 'hook' => 'et_builder_row_added'],
            'divi_template_applied' => ['label' => 'Divi Template Applied', 'hook' => 'et_builder_template_applied'],
            'divi_global_module_updated' => ['label' => 'Divi Global Module Updated', 'hook' => 'et_builder_global_module_updated'],
            'divi_theme_builder_used' => ['label' => 'Divi Theme Builder Used', 'hook' => 'et_theme_builder_template_used'],
            'divi_ab_test_started' => ['label' => 'Divi A/B Test Started', 'hook' => 'et_builder_ab_test_started'],
            'divi_contact_form_submitted' => ['label' => 'Divi Contact Form Submitted', 'hook' => 'et_pb_contact_form_submit'],
            'divi_optin_form_submitted' => ['label' => 'Divi Optin Form Submitted', 'hook' => 'et_pb_signup_form_submit'],
        ];
    }

    public static function get_actions(): array {
        return [];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'divi_theme_activated':
                return [
                    'theme_name' => get_option('stylesheet'),
                    'activated_at' => current_time('mysql'),
                ];

            case 'divi_builder_enabled':
            case 'divi_page_built':
                $post_id = $args[0] ?? 0;
                if (!$post_id) return false;
                
                $post = get_post($post_id);
                if (!$post) return false;

                return [
                    'post_id' => $post_id,
                    'post_title' => $post->post_title,
                    'post_type' => $post->post_type,
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_layout_saved':
            case 'divi_library_item_saved':
                $layout_id = $args[0] ?? 0;
                $layout_data = $args[1] ?? [];
                
                return [
                    'layout_id' => $layout_id,
                    'layout_type' => $layout_data['type'] ?? '',
                    'layout_name' => $layout_data['name'] ?? '',
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_module_added':
            case 'divi_section_added':
            case 'divi_row_added':
                $module_data = $args[0] ?? [];
                
                return [
                    'module_type' => $module_data['type'] ?? '',
                    'module_slug' => $module_data['slug'] ?? '',
                    'post_id' => $module_data['post_id'] ?? 0,
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_template_applied':
                $template_id = $args[0] ?? 0;
                $post_id = $args[1] ?? 0;
                
                return [
                    'template_id' => $template_id,
                    'post_id' => $post_id,
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_global_module_updated':
                $module_id = $args[0] ?? 0;
                
                return [
                    'module_id' => $module_id,
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_theme_builder_used':
                $template_type = $args[0] ?? '';
                $post_id = $args[1] ?? 0;
                
                return [
                    'template_type' => $template_type,
                    'post_id' => $post_id,
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_ab_test_started':
                $test_data = $args[0] ?? [];
                
                return [
                    'test_id' => $test_data['id'] ?? 0,
                    'post_id' => $test_data['post_id'] ?? 0,
                    'user_id' => get_current_user_id(),
                ];

            case 'divi_contact_form_submitted':
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