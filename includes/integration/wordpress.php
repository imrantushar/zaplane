<?php
namespace Zaplane\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zaplane\Classes\IntegrationBase;

class Wordpress extends IntegrationBase {

    public static function get_slug(): string {
        return 'wordpress';
    }

    /* =====================================================
     * TRIGGERS
     * ===================================================== */

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
     * 🔧 Trigger Config Schema (UI ONLY)
     */
    public static function get_trigger_config_schema( string $trigger ): array {

        switch ( $trigger ) {

            case 'publish_post':
            case 'post_updated':
                return [
                    [
                        'key'      => 'post_type',
                        'label'    => 'Post Type',
                        'type'     => 'select',
                        'options'  => self::get_post_type_options(),
                        'required' => true,
                    ],
                    [
                        'key'     => 'post_status',
                        'label'   => 'Post Status',
                        'type'    => 'select',
                        'options' => [
                            ['label' => 'Publish', 'value' => 'publish'],
                            ['label' => 'Draft', 'value' => 'draft'],
                        ],
                    ],
                ];

            case 'user_register':
                return [
                    [
                        'key'   => 'role',
                        'label' => 'User Role',
                        'type'  => 'select',
                        'options' => self::get_user_roles(),
                    ],
                ];

            case 'comment_post':
                return [
                    [
                        'key'   => 'post_id',
                        'label' => 'Post ID (optional)',
                        'type'  => 'expression',
                    ],
                ];
        }

        return [];
    }

    /* =====================================================
     * TRIGGER PAYLOAD
     * ===================================================== */

    public static function resolve_trigger(array $node, array $args) {

        // You may optionally FILTER here using $node['config']

        switch ($node['event']) {

            case 'publish_post':
            case 'post_updated':
            case 'before_delete_post':
                $post = get_post($args[0] ?? 0);
                if (!$post) return false;

                // Example config filter
                if (!empty($node['config']['post_type']) && $post->post_type !== $node['config']['post_type']) {
                    return false;
                }

                return [
                    'post_id'    => $post->ID,
                    'title'      => $post->post_title,
                    'post_type'  => $post->post_type,
                ];

            case 'user_register':
                $user = get_userdata($args[0] ?? 0);
                if (!$user) return false;

                return [
                    'user_id'   => $user->ID,
                    'email'     => $user->user_email,
                    'role'      => $user->roles[0] ?? '',
                ];

            case 'comment_post':
                $comment = get_comment($args[0] ?? 0);
                if (!$comment) return false;

                return [
                    'comment_id' => $comment->comment_ID,
                    'post_id'    => $comment->comment_post_ID,
                    'content'    => $comment->comment_content,
                ];
        }

        return false;
    }

    /* =====================================================
     * ACTIONS
     * ===================================================== */

    public static function get_actions(): array {
        return [
            'create_post'    => ['label' => 'Create Post'],
            'update_post'    => ['label' => 'Update Post'],
            'delete_post'    => ['label' => 'Delete Post'],
            'create_user'    => ['label' => 'Create User'],
            'update_user'    => ['label' => 'Update User'],
            'delete_user'    => ['label' => 'Delete User'],
            'add_comment'    => ['label' => 'Add Comment'],
            'update_option'  => ['label' => 'Update Option'],
        ];
    }

    /**
     * 🔧 Action Config Schema (UI ONLY)
     */
    public static function get_action_config_schema( string $action ): array {

        switch ( $action ) {

            case 'create_post':
                return [
                    [
                        'key'   => 'post_title',
                        'label' => 'Post Title',
                        'type'  => 'expression',
                        'required' => true,
                    ],
                    [
                        'key'   => 'post_content',
                        'label' => 'Content',
                        'type'  => 'textarea',
                    ],
                    [
                        'key'   => 'post_status',
                        'label' => 'Status',
                        'type'  => 'select',
                        'options' => [
                            ['label'=>'Draft','value'=>'draft'],
                            ['label'=>'Publish','value'=>'publish'],
                        ],
                    ],
                ];

            case 'update_option':
                return [
                    [
                        'key'   => 'option_name',
                        'label' => 'Option Name',
                        'type'  => 'text',
                        'required' => true,
                    ],
                    [
                        'key'   => 'value',
                        'label' => 'Value',
                        'type'  => 'expression',
                    ],
                ];
        }

        return [];
    }

    /* =====================================================
     * ACTION EXECUTION
     * ===================================================== */

    public static function execute_node(array $node, array $input): array {

        $config = $node['config'] ?? [];

        switch ($node['config']['action'] ?? '') {

            case 'create_post':
                $post_id = wp_insert_post([
                    'post_title'   => $config['post_title'] ?? '',
                    'post_content' => $config['post_content'] ?? '',
                    'post_status'  => $config['post_status'] ?? 'draft',
                ]);
                return ['port'=>'main','data'=>['post_id'=>$post_id]];

            case 'update_option':
                update_option($config['option_name'], $config['value']);
                return ['port'=>'main','data'=>[]];
        }

        return ['port'=>'main','data'=>$input];
    }

    /* =====================================================
     * HELPERS (UI)
     * ===================================================== */

    protected static function get_post_type_options(): array {
        return array_map(
            fn($pt) => ['label' => $pt->label, 'value' => $pt->name],
            get_post_types(['public'=>true],'objects')
        );
    }

    protected static function get_user_roles(): array {
        global $wp_roles;
        return array_map(
            fn($name, $key) => ['label'=>$name,'value'=>$key],
            $wp_roles->roles,
            array_keys($wp_roles->roles)
        );
    }
}
