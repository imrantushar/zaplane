<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Profilebuilder extends IntegrationBase {

    public static function get_slug(): string {
        return 'profilebuilder';
    }

    public static function get_triggers(): array {
        return [
       
            'wppb_register_success' => ['label' => 'User Registration', 'hook' => 'wppb_register_success'],
            'wppb_edit_profile_success' => ['label' => 'User Profile Update', 'hook' => 'wppb_edit_profile_success'],
            'wppb_activate_user' => ['label' => 'User Email Confirmation', 'hook' => 'wppb_activate_user'],
            'wppb_after_sending_email' => ['label' => 'Email Send By Profile Builder', 'hook' => 'wppb_after_sending_email'],
            'wppb_after_user_approval' => ['label' => 'User Approved By Admin', 'hook' => 'wppb_after_user_approval'],
            'wppb_after_user_unapproval' => ['label' => 'User UnApproved By Admin', 'hook' => 'wppb_after_user_unapproval'],

        ];
    }

    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'wppb_register_success':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];
  ray($args);
                return [
                    'form_id' => 'hello',
               
                ];
                 case 'wppb_edit_profile_success':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];
                 ray($args);
                return [
                    'submitted_at' => current_time('mysql'),
                ];
                 case 'wppb_activate_user':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];
   ray($args);
                return [
             
                    'submitted_at' => current_time('mysql'),
                ];
                 case 'wppb_after_sending_email':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];
ray($args);
                return [
                
                    'submitted_at' => current_time('mysql'),
                ];
                 case 'wppb_after_user_approval':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];
ray($args);
                return [
               
                    'submitted_at' => current_time('mysql'),
                ];
                 case 'wppb_after_user_unapproval':
                $form_fields = $args[0] ?? [];
                $form_meta = $args[2] ?? [];
ray($args);
                return [
                 
                    'submitted_at' => current_time('mysql'),
                ];
        }

        return false;
    }

    public static function execute_node(array $node, array $input): array {
        return ['port' => 'main', 'data' => $input];
    }
}