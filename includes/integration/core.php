<?php
namespace Zaplane\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Classes\IntegrationBase;

class Core extends IntegrationBase {

    public static function get_slug(): string {
        return 'wordpress';
    }

    /**
     * Triggers
     */
    public static function get_triggers(): array {
        return [
            'publish_post'       => ['label' => 'Post Published', 'hook' => 'publish_post'],
            'post_updated'       => ['label' => 'Post Updated', 'hook' => 'post_updated'],
            'before_delete_post' => ['label' => 'Post Deleted', 'hook' => 'before_delete_post'],
            'user_register'      => ['label' => 'User Registered', 'hook' => 'user_register'],
            'profile_update'     => ['label' => 'User Updated', 'hook' => 'profile_update'],
            'comment_post'       => ['label' => 'Comment Added', 'hook' => 'comment_post'],
            'edit_comment'       => ['label' => 'Comment Updated', 'hook' => 'edit_comment'],
            'delete_comment'     => ['label' => 'Comment Deleted', 'hook' => 'delete_comment'],
            'update_option'      => ['label' => 'Option Updated', 'hook' => 'update_option'],
        ];
    }

    /**
     * Resolve payload for triggers
     */
    public static function resolve_trigger(array $node, array $args) {
        switch ($node['event']) {
            case 'publish_post':
            case 'post_updated':
            case 'before_delete_post':
                $post = get_post($args[0] ?? 0);
                if (!$post) return false;
                return [
                    'post_id'    => $post->ID,
                    'title'      => $post->post_title,
                    'post_type'  => $post->post_type,
                ];
            case 'user_register':
            case 'profile_update':
                $user = get_userdata($args[0] ?? 0);
                if (!$user) return false;
                return [
                    'user_id'   => $user->ID,
                    'user_login'=> $user->user_login,
                    'email'     => $user->user_email,
                ];
            case 'comment_post':
            case 'edit_comment':
            case 'delete_comment':
                $comment = get_comment($args[0] ?? 0);
                if (!$comment) return false;
                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                ];
            case 'update_option':
                return [
                    'option_name'  => $args[0] ?? '',
                    'old_value'    => $args[1] ?? null,
                    'new_value'    => $args[2] ?? null,
                ];
            default:
                return false;
        }
    }

    /**
     * Actions
     */
    public static function get_actions(): array {
        return [
            'create_post'  => ['label' => 'Create Post'],
            'update_post'  => ['label' => 'Update Post'],
            'delete_post'  => ['label' => 'Delete Post'],
            'create_user'  => ['label' => 'Create User'],
            'update_user'  => ['label' => 'Update User'],
            'delete_user'  => ['label' => 'Delete User'],
            'add_comment'  => ['label' => 'Add Comment'],
            'update_comment'=> ['label' => 'Update Comment'],
            'delete_comment'=> ['label' => 'Delete Comment'],
            'update_option'=> ['label' => 'Update Option'],
        ];
    }

    /**
     * Execute action node
     */
    public static function execute_node(array $node, array $input): array {
        switch ($node['config']['action'] ?? '') {
            case 'create_post':
                $post_id = wp_insert_post($node['config']['data'] ?? []);
                return ['port'=>'main','data'=>['post_id'=>$post_id]];

            case 'update_post':
                $post_id = wp_update_post($node['config']['data'] ?? []);
                return ['port'=>'main','data'=>['post_id'=>$post_id]];

            case 'delete_post':
                wp_delete_post($node['config']['data']['ID'] ?? 0);
                return ['port'=>'main','data'=>[]];

            case 'create_user':
                $user_id = wp_create_user(...($node['config']['data'] ?? []));
                return ['port'=>'main','data'=>['user_id'=>$user_id]];

            case 'update_user':
                $user_id = wp_update_user($node['config']['data'] ?? []);
                return ['port'=>'main','data'=>['user_id'=>$user_id]];

            case 'delete_user':
                wp_delete_user($node['config']['data']['ID'] ?? 0);
                return ['port'=>'main','data'=>[]];

            case 'add_comment':
                $comment_id = wp_insert_comment($node['config']['data'] ?? []);
                return ['port'=>'main','data'=>['comment_id'=>$comment_id]];

            case 'update_comment':
                wp_update_comment($node['config']['data'] ?? []);
                return ['port'=>'main','data'=>[]];

            case 'delete_comment':
                wp_delete_comment($node['config']['data']['comment_ID'] ?? 0);
                return ['port'=>'main','data'=>[]];

            case 'update_option':
                update_option($node['config']['data']['option_name'] ?? '', $node['config']['data']['value'] ?? '');
                return ['port'=>'main','data'=>[]];

            default:
                return ['port'=>'main','data'=>$input];
        }
    }

}
