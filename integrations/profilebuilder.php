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

    public static function get_triggers(): array
    {
        return [
            'user_registration' => [
                'label' => 'User Registration',
                'hook'  => 'wppb_register_success'
            ],
            'user_profile_update' => [
                'label' => 'User Profile Update',
                'hook'  => 'wppb_edit_profile_success'
            ],
            'user_email_confirmation' => [
                'label' => 'User Email Confirmation',
                'hook'  => 'wppb_activate_user'
            ],
            'email_send_by_profile_builder' => [
                'label' => 'Email Send By Profile Builder',
                'hook'  => 'wppb_after_sending_email'
            ],
            'user_approved_by_admin' => [
                'label' => 'User Approved By Admin',
                'hook'  => 'wppb_after_user_approval'
            ],
            'user_unapproved_by_admin' => [
                'label' => 'User UnApproved By Admin',
                'hook'  => 'wppb_after_user_unapproval'
            ],
        ];
    }

    /**
     * ট্রিগারের ডেটা রিজল্ভ করে
     *
     * @param array $node নোড কনফিগারেশন
     * @param array $args হুক আর্গুমেন্ট
     * @return array|false
     */
    public static function resolve_trigger(array $node, array $args)
    {
        $event = $node['event'];
        $data  = [];

        switch ($event) {
            case 'user_registration':
                // হুক: wppb_register_success( $request, $form_name, $user_id )
                if ( isset( $args[0], $args[1], $args[2] ) ) {
                    $data = [
                        'user_id'   => (int) $args[2],
                        'form_name' => sanitize_text_field( $args[1] ),
                        'request'   => $args[0], // সংবেদনশীল তথ্য থাকতে পারে, প্রয়োজনে ফিল্টার করুন
                    ];
                    $data = array_merge( $data, self::get_user_info( $args[2] ) );
                }
                break;

            case 'user_profile_update':
                // হুক: wppb_edit_profile_success( $request, $form_name, $user_id )
                if ( isset( $args[0], $args[1], $args[2] ) ) {
                    $data = [
                        'user_id'   => (int) $args[2],
                        'form_name' => sanitize_text_field( $args[1] ),
                        'request'   => $args[0],
                    ];
                    $data = array_merge( $data, self::get_user_info( $args[2] ) );
                }
                break;

            case 'user_email_confirmation':
                // হুক: wppb_activate_user( $user_id, $password, $meta )
                if ( isset( $args[0], $args[1], $args[2] ) ) {
                    $data = [
                        'user_id'  => (int) $args[0],
                        'password' => $args[1], // এনক্রিপ্টেড পাসওয়ার্ড
                        'meta'     => $args[2],
                    ];
                    $data = array_merge( $data, self::get_user_info( $args[0] ) );
                }
                break;

            case 'email_send_by_profile_builder':
                // হুক: wppb_after_sending_email( $sent, $to, $subject, $message, $send_email, $context )
                if ( isset( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5] ) ) {
                    $data = [
                        'sent'       => (bool) $args[0],
                        'to'         => sanitize_email( $args[1] ),
                        'subject'    => sanitize_text_field( $args[2] ),
                        'message'    => wp_kses_post( $args[3] ),
                        'send_email' => $args[4], // অ্যারে বা অন্যান্য
                        'context'    => $args[5],
                    ];
                }
                break;

            case 'user_approved_by_admin':
                // হুক: wppb_after_user_approval( $user_id )
                if ( isset( $args[0] ) ) {
                    $user_id = (int) $args[0];
                    $data    = [ 'user_id' => $user_id ];
                    $data    = array_merge( $data, self::get_user_info( $user_id ) );
                }
                break;

            case 'user_unapproved_by_admin':
                // হুক: wppb_after_user_unapproval( $user_id )
                if ( isset( $args[0] ) ) {
                    $user_id = (int) $args[0];
                    $data    = [ 'user_id' => $user_id ];
                    $data    = array_merge( $data, self::get_user_info( $user_id ) );
                }
                break;

            default:
                return false;
        }

        // ট্রিগার হওয়ার সময় সংযুক্ত করুন
        if ( ! empty( $data ) ) {
            $data['triggered_at'] = current_time( 'mysql' );
        }

        return $data;
    }

    /**
     * ইউজারের বেসিক তথ্য সংগ্রহ করে
     *
     * @param int $user_id
     * @return array
     */
    private static function get_user_info( $user_id )
    {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return [];
        }

        return [
            'user_email'   => $user->user_email,
            'user_login'   => $user->user_login,
            'display_name' => $user->display_name,
            'user_url'     => $user->user_url,
            'registered'   => $user->user_registered,
        ];
    }

    public static function execute_node(array $node, array $input): array
    {
        // নোড এক্সিকিউশন লজিক (প্রয়োজনে পরিবর্তন করুন)
        return ['port' => 'main', 'data' => $input];
    }
}
