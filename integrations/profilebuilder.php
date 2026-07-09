<?php

namespace Zaplane\Integrations;

if ( ! defined( 'ABSPATH' ) ) exit;

use Zaplane\Framework\Classes\IntegrationBase;

class Profilebuilder extends IntegrationBase
{

    public static function get_slug(): string
    {
        return 'profilebuilder';
    }

    public static function get_name(): string
    {
        return 'Profile Builder';
    }

    public static function get_icon(): string
    {
        return 'profilebuilder.svg';
    }

    public static function get_triggers(): array
    {
        return [

            'user_registration' => [
                'label' => 'User Registration',
                'hook' => 'wppb_register_success'
            ],
            'user_profile_update' => [
                'label' => 'User Profile Update',
                'hook' => 'wppb_edit_profile_success'
            ],
            'user_email_confirmation' => [
                'label' => 'User Email Confirmation',
                'hook' => 'wppb_activate_user'
            ],
            'email_send_by_profile_builder' => [
                'label' => 'Email Send By Profile Builder',
                'hook' => 'wppb_after_sending_email'
            ],
            'user_approved_by_admin' => [
                'label' => 'User Approved By Admin',
                'hook' => 'wppb_after_user_approval'
            ],
            'user_unapproved_by_admin' => [
                'label' => 'User UnApproved By Admin',
                'hook' => 'wppb_after_user_unapproval'
            ],

        ];
    }

    public static function resolve_trigger(array $node, array $args)
    {
        $event = $node['event'] ?? '';

        switch ($event) {

            case 'user_registration':
            case 'user_profile_update':

                $form_data = $args[0] ?? [];
                $user_id   = isset($args[2]) ? (int) $args[2] : 0;

                if (empty($form_data)) {
                    return [];
                }

                unset($form_data['pass1'], $form_data['pass2'], $form_data['password']);

                return [
                    'user_id'   => $user_id,
                    'form_data' => $form_data,
                    'submitted_at' => current_time('mysql'),
                ];


            case 'user_email_confirmation':

                $user_id = isset($args[0]) ? (int) $args[0] : 0;
                $meta    = $args[2] ?? [];

                if (!$user_id) {
                    return [];
                }

                return [
                    'user_id'      => $user_id,
                    'meta'         => $meta,
                    'submitted_at' => current_time('mysql'),
                ];


            case 'email_send_by_profile_builder':

                $to      = isset($args[1]) ? sanitize_email($args[1]) : '';
                $subject = isset($args[2]) ? sanitize_text_field($args[2]) : '';
                $message = $args[3] ?? '';

                if (!$to) {
                    return [];
                }

                return [
                    'mail_to'        => $to,
                    'subject'        => $subject,
                    'message_preview' => wp_trim_words(wp_strip_all_tags($message), 20),
                    'sent'           => (bool) ($args[0] ?? false),
                    'submitted_at'   => current_time('mysql'),
                ];


            case 'user_approved_by_admin':
            case 'user_unapproved_by_admin':

                $user_id = isset($args[0]) ? (int) $args[0] : 0;

                if (!$user_id) {
                    return [];
                }

                return [
                    'user_id'      => $user_id,
                    'submitted_at' => current_time('mysql'),
                ];
        }

        return false;
    }

    public static function get_trigger_sample_output( string $event ): array
    {
        $submitted_at = current_time( 'mysql' );

        $user = [
            'user_id'      => 42,
            'submitted_at' => $submitted_at,
        ];

        $profile = [
            'user_id'   => 42,
            'form_data' => [
                'username'   => 'janedoe',
                'email'      => 'jane.doe@example.com',
                'first_name' => 'Jane',
                'last_name'  => 'Doe',
                'nickname'   => 'jane',
                'website'    => 'https://example.com',
            ],
            'submitted_at' => $submitted_at,
        ];

        $samples = [
            'user_registration'   => $profile,
            'user_profile_update' => $profile,

            'user_email_confirmation' => [
                'user_id'      => 42,
                'meta'         => [
                    'activation_key' => 'a1b2c3d4e5',
                    'user_login'     => 'janedoe',
                    'user_email'     => 'jane.doe@example.com',
                ],
                'submitted_at' => $submitted_at,
            ],

            'email_send_by_profile_builder' => [
                'mail_to'         => 'jane.doe@example.com',
                'subject'         => 'Welcome to our website',
                'message_preview' => 'Hi Jane, thank you for registering. Please confirm your email address to activate your account and get started.',
                'sent'            => true,
                'submitted_at'    => $submitted_at,
            ],

            'user_approved_by_admin'   => $user,
            'user_unapproved_by_admin' => $user,
        ];

        if ( isset( $samples[ $event ] ) ) {
            return $samples[ $event ];
        }

        if ( 0 === strpos( $event, 'email_' ) ) {
            return $samples['email_send_by_profile_builder'];
        }

        if ( 0 === strpos( $event, 'user_' ) ) {
            return $user;
        }

        return $user;
    }

    public static function execute_node(array $node, array $input): array
    {
        return ['port' => 'main', 'data' => $input];
    }
}
