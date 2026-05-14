<?php

if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        return (object)[
            'ID'           => $user_id,
            'user_login'   => 'testuser',
            'user_email'   => 'test@example.com',
            'nickname'     => 'Tester',
            'display_name' => 'Test User',
            'roles'        => ['subscriber'],
        ];
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email)
    {
        return 1; // always return user ID
    }
}

if (!function_exists('bp_activity_add')) {
    function bp_activity_add($args)
    {
        return rand(100, 999); // fake activity id
    }
}

if (!function_exists('groups_get_group')) {
    function groups_get_group($id)
    {
        return (object)[
            'id'     => $id,
            'name'   => 'Test Group',
            'status' => 'public',
            'creator_id' => 1
        ];
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($id)
    {
        return "https://example.com/avatar/$id.png";
    }
}