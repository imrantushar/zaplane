<?php

// ------------------------------
// WordPress Core Mocks
// ------------------------------

if (!function_exists('email_exists')) {
    function email_exists($email) {
        return $email === 'user@test.com' ? 1 : false;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        if ($user_id !== 1) return false;

        return (object)[
            'ID' => 1,
            'user_login' => 'testuser',
            'user_email' => 'user@test.com',
            'nickname' => 'Tester',
            'display_name' => 'Test User',
            'roles' => ['subscriber']
        ];
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        if ($field === 'email' && $value === 'user@test.com') {
            return get_userdata(1);
        }
        return false;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta() {
        return 'Test';
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url() {
        return 'http://avatar.test/img.png';
    }
}

// ------------------------------
// BuddyBoss / BP mocks
// ------------------------------

if (!function_exists('bp_core_current_time')) {
    function bp_core_current_time() {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('bp_is_active')) {
    function bp_is_active($component) {
        return true;
    }
}

if (!function_exists('groups_create_group')) {
    function groups_create_group($args) {
        return 10;
    }
}

if (!function_exists('groups_get_group')) {
    function groups_get_group($id) {
        return (object)[
            'id' => $id,
            'name' => 'Demo Group',
            'status' => 'public',
            'creator_id' => 1
        ];
    }
}

if (!function_exists('groups_join_group')) {
    function groups_join_group($group_id, $user_id) {
        return true;
    }
}

if (!function_exists('groups_leave_group')) {
    function groups_leave_group($group_id, $user_id) {
        return true;
    }
}

if (!function_exists('friends_add_friend')) {
    function friends_add_friend($a, $b) {
        return true;
    }
}

if (!function_exists('friends_remove_friend')) {
    function friends_remove_friend($a, $b) {
        return true;
    }
}

if (!function_exists('bp_activity_add')) {
    function bp_activity_add($args) {
        return rand(100, 999);
    }
}

if (!function_exists('messages_new_message')) {
    function messages_new_message($args) {
        return 55;
    }
}

if (!function_exists('bp_notifications_add_notification')) {
    function bp_notifications_add_notification($args) {
        return rand(200, 300);
    }
}

if (!function_exists('bbp_insert_topic')) {
    function bbp_insert_topic($args) {
        return 101;
    }
}

if (!function_exists('bbp_insert_reply')) {
    function bbp_insert_reply($args) {
        return 202;
    }
}