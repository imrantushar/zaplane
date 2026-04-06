<?php
// File: WP_Mocks.php
namespace Zaplane\Tests\Mocks;

use stdClass;

class WP_Mocks {

    public static function init()
    {
        global $wpdb;

        // Mock $wpdb
        $wpdb = new stdClass();
        $wpdb->pmpro_membership_levels = 'pmpro_membership_levels';
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';

        // Mock database methods
        $wpdb->get_results = function($sql) {
            if (stripos($sql, 'pmpro_membership_levels') !== false) {
                return [
                    (object)['id' => 1, 'name' => 'Level 1'],
                    (object)['id' => 2, 'name' => 'Level 2'],
                ];
            }
            return [];
        };

        $wpdb->get_row = function($sql, $output_type = OBJECT) {
            if (stripos($sql, 'WHERE id = 1') !== false) {
                return (object)['id' => 1, 'name' => 'Level 1', 'expiration_number' => 0, 'expiration_period' => ''];
            }
            if (stripos($sql, 'WHERE id = 2') !== false) {
                return (object)['id' => 2, 'name' => 'Level 2', 'expiration_number' => 0, 'expiration_period' => ''];
            }
            return null;
        };

        // WordPress function mocks
        if (!function_exists('get_userdata')) {
            function get_userdata($user_id) {
                return (object)[
                    'ID' => $user_id,
                    'user_login' => 'user'.$user_id,
                    'user_email' => 'user'.$user_id.'@example.com',
                    'display_name' => 'User '.$user_id,
                    'nickname' => 'user'.$user_id,
                    'roles' => ['subscriber']
                ];
            }
        }

        if (!function_exists('get_user_meta')) {
            function get_user_meta($user_id, $key, $single = true) {
                return $key.'_value';
            }
        }

        if (!function_exists('get_avatar_url')) {
            function get_avatar_url($user_id) {
                return 'https://example.com/avatar/'.$user_id;
            }
        }

        if (!function_exists('sanitize_email')) {
            function sanitize_email($email) {
                return filter_var($email, FILTER_SANITIZE_EMAIL);
            }
        }

        if (!function_exists('get_user_by')) {
            function get_user_by($field, $value) {
                if ($value === 'exists@example.com') {
                    return (object)['ID' => 1, 'user_email' => $value];
                }
                return false;
            }
        }

        // PMPro function mocks
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            function pmpro_getMembershipLevelForUser($user_id) {
                return (object)['ID' => 2];
            }
        }

        if (!function_exists('pmpro_changeMembershipLevel')) {
            function pmpro_changeMembershipLevel($level_id, $user_id = 0) {
                return true;
            }
        }

        if (!function_exists('pmpro_getMembershipLevelsForUser')) {
            function pmpro_getMembershipLevelsForUser($user_id) {
                return [(object)['ID' => 1, 'name' => 'Level 1']];
            }
        }

        if (!function_exists('pmpro_cancelMembershipLevel')) {
            function pmpro_cancelMembershipLevel($level_id, $user_id) {
                return true;
            }
        }
    }
}